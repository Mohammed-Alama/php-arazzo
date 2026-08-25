<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

use Ecosystem\GhCli;

require_once dirname(__DIR__) . '/GhCli.php';

final class GithubApiIngestor
{
    /**
     * Poll via `gh api` (preferred) with curl fallback.
     *
     * @param string $repo e.g. OAI/Arazzo-Specification
     * @param string[] $endpoints e.g. [releases, pulls, issues]
     * @param array<string,string> $etagCache key -> etag (kept for curl fallback; gh ignores)
     *
     * @return array<int,array<string,mixed>> raw normalized items (pre-Normalizer but with source/type/externalId/title/url/publishedAt)
     */
    public static function poll(string $repo, array $endpoints, array &$etagCache, ?string $token, int $limitPerEndpoint = 20, bool $verbose = false): array
    {
        $useGh = GhCli::isAvailable();
        if ($verbose) {
            fwrite(STDERR, $useGh ? "  using gh cli\n" : "  gh not available, using curl fallback\n");
        }

        $out = [];
        foreach ($endpoints as $ep) {
            $ghEndpoint = match ($ep) {
                'releases' => "repos/{$repo}/releases?per_page={$limitPerEndpoint}",
                'tags' => "repos/{$repo}/tags?per_page={$limitPerEndpoint}",
                'pulls' => "repos/{$repo}/pulls?state=all&per_page={$limitPerEndpoint}&sort=updated&direction=desc",
                'issues' => "repos/{$repo}/issues?state=all&per_page={$limitPerEndpoint}&sort=updated&direction=desc",
                'commits' => "repos/{$repo}/commits?per_page={$limitPerEndpoint}",
                default => null,
            };
            if ($ghEndpoint === null) {
                continue;
            }

            $items = null;
            if ($useGh) {
                $items = GhCli::api($ghEndpoint, false, $verbose);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $mapped = self::map($repo, $ep, $item);
                        if ($mapped !== null) {
                            $out[] = $mapped;
                        }
                    }
                    usleep(150000);

                    continue;
                }
                if ($verbose) {
                    fwrite(STDERR, "  [gh] {$repo} {$ep} gh api returned null, falling back to curl\n");
                }
            }

            // curl fallback (with ETag)
            $url = "https://api.github.com/{$ghEndpoint}";
            $cacheKey = $repo . ':' . $ep;
            $etag = $etagCache[$cacheKey] ?? null;
            $res = self::getJson($url, $etag, $token, $verbose);

            if ($res['status'] === 304) {
                continue; // not modified
            }
            if ($res['status'] >= 200 && $res['status'] < 300 && is_array($res['json'])) {
                if ($res['etag'] !== null) {
                    $etagCache[$cacheKey] = $res['etag'];
                }
                foreach ($res['json'] as $item) {
                    $mapped = self::map($repo, $ep, $item);
                    if ($mapped !== null) {
                        $out[] = $mapped;
                    }
                }
            } else {
                if ($verbose) {
                    fwrite(STDERR, "  [warn] {$repo} {$ep} HTTP {$res['status']}\n");
                }
            }
            usleep(150000);
        }

        return $out;
    }

    /** @return array{status:int, json:mixed, etag:?string, headers:string} */
    private static function getJson(string $url, ?string $etag, ?string $token, bool $verbose): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: php-arazzo-ecosystem-feed',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($etag !== null) {
            $headers[] = 'If-None-Match: ' . $etag;
        }
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if ($resp === false) {
            return ['status' => 0, 'json' => null, 'etag' => null, 'headers' => ''];
        }

        $headerRaw = substr($resp, 0, $hsize);
        $body = substr($resp, $hsize);
        $newEtag = null;
        foreach (explode("\r\n", $headerRaw) as $line) {
            if (stripos($line, 'etag:') === 0) {
                $newEtag = trim(substr($line, 5));
            }
            if (stripos($line, 'retry-after:') === 0 && $verbose) {
                fwrite(STDERR, '  Retry-After: ' . trim(substr($line, 12)) . "\n");
            }
        }
        $json = null;
        if ($body !== '' && $body !== '[]') {
            $json = json_decode($body, true);
        } elseif ($body === '[]') {
            $json = [];
        }

        return ['status' => $code, 'json' => $json, 'etag' => $newEtag, 'headers' => $headerRaw];
    }

    /** @param array<string,mixed> $item @return array<string,mixed>|null */
    private static function map(string $repo, string $ep, array $item): ?array
    {
        return match ($ep) {
            'releases' => [
                'source' => $repo,
                'type' => 'release',
                'externalId' => 'release:' . ($item['tag_name'] ?? $item['id'] ?? ''),
                'title' => $item['name'] ?? $item['tag_name'] ?? 'release',
                'url' => $item['html_url'] ?? "https://github.com/{$repo}/releases/tag/" . ($item['tag_name'] ?? ''),
                'publishedAt' => $item['published_at'] ?? $item['created_at'] ?? gmdate('c'),
                'body' => $item['body'] ?? '',
                'labels' => [],
                'state' => 'published',
            ],
            'tags' => [
                'source' => $repo,
                'type' => 'tag',
                'externalId' => 'tag:' . ($item['name'] ?? ''),
                'title' => 'tag ' . ($item['name'] ?? ''),
                'url' => "https://github.com/{$repo}/releases/tag/" . ($item['name'] ?? ''),
                'publishedAt' => gmdate('c'), // tags lack timestamp; use now but dedup via id
                'body' => $item['commit']['sha'] ?? '',
                'labels' => [],
                'state' => 'published',
            ],
            'pulls' => [
                'source' => $repo,
                'type' => 'pr',
                'externalId' => 'pr:' . ($item['number'] ?? ''),
                'title' => $item['title'] ?? 'PR',
                'url' => $item['html_url'] ?? "https://github.com/{$repo}/pull/" . ($item['number'] ?? ''),
                'publishedAt' => $item['updated_at'] ?? $item['created_at'] ?? gmdate('c'),
                'body' => $item['body'] ?? '',
                'labels' => array_map(fn ($l) => $l['name'] ?? '', $item['labels'] ?? []),
                'state' => $item['state'] ?? '',
                'merged' => $item['merged_at'] !== null,
            ],
            'issues' => isset($item['pull_request']) ? null : [
                'source' => $repo,
                'type' => 'issue',
                'externalId' => 'issue:' . ($item['number'] ?? ''),
                'title' => $item['title'] ?? 'issue',
                'url' => $item['html_url'] ?? "https://github.com/{$repo}/issues/" . ($item['number'] ?? ''),
                'publishedAt' => $item['updated_at'] ?? $item['created_at'] ?? gmdate('c'),
                'body' => $item['body'] ?? '',
                'labels' => array_map(fn ($l) => $l['name'] ?? '', $item['labels'] ?? []),
                'state' => $item['state'] ?? '',
            ],
            'commits' => [
                'source' => $repo,
                'type' => 'commit',
                'externalId' => 'commit:' . substr($item['sha'] ?? '', 0, 7),
                'title' => trim(explode("\n", $item['commit']['message'] ?? 'commit')[0]),
                'url' => $item['html_url'] ?? "https://github.com/{$repo}/commit/" . ($item['sha'] ?? ''),
                'publishedAt' => $item['commit']['committer']['date'] ?? $item['commit']['author']['date'] ?? gmdate('c'),
                'body' => $item['commit']['message'] ?? '',
                'labels' => [],
                'state' => '',
            ],
            default => null,
        };
    }
}
