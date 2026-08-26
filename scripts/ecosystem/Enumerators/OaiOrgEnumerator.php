<?php

declare(strict_types=1);

namespace Ecosystem\Enumerators;

use Ecosystem\GhCli;

require_once dirname(__DIR__).'/GhCli.php';

final class OaiOrgEnumerator
{
    public const ORG = 'OAI';

    public const API = 'https://api.github.com/orgs/OAI/repos?per_page=100';

    /**
     * Fetch all OAI repos via `gh api` (preferred) with curl fallback.
     * Keeps ETag cache for compatibility but gh path ignores 304 and always refreshes.
     *
     * @param  string  $etagCachePath  .cache/ecosystem/etags.json
     * @return array{repos: array<int,array<string,mixed>>, etag: ?string, cached: bool}
     */
    public static function fetch(string $etagCachePath, ?string $token = null, bool $verbose = false): array
    {
        // Prefer gh cli
        $ghRepos = GhCli::apiOrNull('orgs/OAI/repos?per_page=100', true, $verbose);
        if (is_array($ghRepos) && $ghRepos !== [] && isset($ghRepos[0]['full_name'])) {
            $dir = dirname($etagCachePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $existing = is_file($etagCachePath) ? (json_decode((string) file_get_contents($etagCachePath), true) ?? []) : [];
            $existing['OAI_repos_data'] = $ghRepos;
            // keep etag key but gh does not provide it; cache repo list for offline fallback
            file_put_contents($etagCachePath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            return ['repos' => $ghRepos, 'etag' => $existing['OAI_repos_etag'] ?? null, 'cached' => false];
        }

        // Fallback to curl API with ETag
        $etag = null;
        $cached = [];
        if (is_file($etagCachePath)) {
            $c = json_decode((string) file_get_contents($etagCachePath), true);
            $etag = $c['OAI_repos_etag'] ?? null;
            $cached = $c['OAI_repos_data'] ?? [];
        }

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: php-arazzo-ecosystem-feed',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($etag !== null) {
            $headers[] = 'If-None-Match: '.$etag;
        }
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        $ch = curl_init(self::API);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($resp === false) {
            return ['repos' => $cached, 'etag' => $etag, 'cached' => true];
        }

        $headerRaw = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);

        if ($code === 304) {
            return ['repos' => $cached, 'etag' => $etag, 'cached' => true];
        }

        if ($code >= 200 && $code < 300) {
            $newEtag = null;
            foreach (explode("\r\n", $headerRaw) as $line) {
                if (stripos($line, 'etag:') === 0) {
                    $newEtag = trim(substr($line, 5));
                }
            }
            $repos = json_decode($body, true);
            if (is_array($repos)) {
                $dir = dirname($etagCachePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $existing = is_file($etagCachePath) ? (json_decode((string) file_get_contents($etagCachePath), true) ?? []) : [];
                $existing['OAI_repos_etag'] = $newEtag;
                $existing['OAI_repos_data'] = $repos;
                file_put_contents($etagCachePath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

                return ['repos' => $repos, 'etag' => $newEtag, 'cached' => false];
            }
        }

        return ['repos' => $cached, 'etag' => $etag, 'cached' => true];
    }

    /**
     * Diff live repos vs committed sources.oai.json and return summary.
     *
     * @param  array<int,array<string,mixed>>  $live
     * @param  array<int,array<string,mixed>>  $committed
     * @return array{added: string[], removed: string[], countLive: int, countCommitted: int}
     */
    public static function diff(array $live, array $committed): array
    {
        $liveNames = array_map(fn ($r) => $r['full_name'] ?? $r['fullName'] ?? '', $live);
        $committedNames = array_map(fn ($r) => $r['full_name'] ?? $r['fullName'] ?? '', $committed);
        $added = array_values(array_diff($liveNames, $committedNames));
        $removed = array_values(array_diff($committedNames, $liveNames));

        return ['added' => $added, 'removed' => $removed, 'countLive' => count($liveNames), 'countCommitted' => count($committedNames)];
    }

    /**
     * Extract github.com refs from markdown inside a repo (for "inside open api gh repo docs" requirement).
     *
     * @return string[]
     */
    public static function extractGithubRefs(string $markdown): array
    {
        preg_match_all('#https://github\.com/([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)#', $markdown, $m);

        return array_values(array_unique($m[1] ?? []));
    }
}
