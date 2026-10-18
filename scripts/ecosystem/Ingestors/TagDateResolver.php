<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

use Ecosystem\GhCli;

require_once dirname(__DIR__).'/GhCli.php';

/**
 * Resolves real tag dates for repos that have **no GitHub Releases** (§5.4).
 *
 * The REST `/tags` endpoint returns no timestamp and is not ordered by date, so the
 * old code stamped every tag with `gmdate('c')` and every historical tag looked new
 * on every poll (F2). This resolver asks the GraphQL `refs` connection, ordered by
 * `TAG_COMMIT_DATE`, for `target.tagger.date` (annotated tag) or
 * `target.committedDate` (lightweight tag) — a provider timestamp, never the run clock.
 *
 * `toObservations()` is pure so it can be exercised against a recorded GraphQL payload.
 */
final class TagDateResolver
{
    /** GraphQL query used to fetch the newest tags for a repo, date-ordered desc. */
    public static function query(): string
    {
        return <<<'GQL'
        query ($owner: String!, $name: String!, $first: Int!) {
          repository(owner: $owner, name: $name) {
            refs(refPrefix: "refs/tags/", first: $first, orderBy: {field: TAG_COMMIT_DATE, direction: DESC}) {
              nodes {
                name
                target {
                  ... on Commit { committedDate }
                  ... on Tag { tagger { date } }
                }
              }
            }
          }
        }
        GQL;
    }

    /**
     * Fetch tags with real dates for `$repo`, newest first.
     *
     * @param  array<string,mixed>|null  $watermarks  state watermarks (for early-stop at `lastTag`)
     * @return array<int,array<string,mixed>> observations ready for the Normalizer/Differ
     */
    public static function fetch(string $repo, int $limit, ?string $token = null, bool $verbose = false, ?array $watermarks = null): array
    {
        [$owner, $name] = array_pad(explode('/', $repo, 2), 2, '');
        if ($owner === '' || $name === '') {
            return [];
        }

        $response = GhCli::graphql(self::query(), ['owner' => $owner, 'name' => $name, 'first' => $limit], $verbose);
        if ($response === null) {
            $response = self::fetchViaCurl($owner, $name, $limit, $token, $verbose);
        }
        if ($response === null) {
            return [];
        }

        return self::toObservations($repo, self::nodes($response), $limit, $watermarks);
    }

    /**
     * Extract `data.repository.refs.nodes` from a GraphQL response.
     *
     * @param  array<string,mixed>  $response
     * @return array<int,array<string,mixed>>
     */
    public static function nodes(array $response): array
    {
        $nodes = $response['data']['repository']['refs']['nodes'] ?? null;

        return is_array($nodes) ? array_values(array_filter($nodes, 'is_array')) : [];
    }

    /**
     * Map date-ordered tag nodes to observations, stopping at the stored `lastTag`
     * boundary so a quiet run does no redundant work (§5.4, A9).
     *
     * @param  array<int,array<string,mixed>>  $nodes
     * @param  array<string,mixed>|null  $watermarks
     * @return array<int,array<string,mixed>>
     */
    public static function toObservations(string $repo, array $nodes, int $limit, ?array $watermarks = null): array
    {
        $lastTag = $watermarks['sources'][$repo]['endpoints']['tags']['lastTag'] ?? null;
        $lastTag = is_string($lastTag) && $lastTag !== '' ? $lastTag : null;

        $out = [];
        $count = 0;
        foreach ($nodes as $node) {
            $name = (string) ($node['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if ($lastTag !== null && $name === $lastTag) {
                break; // reached the last tag we already saw — everything after is older
            }
            $date = self::dateOf($node);
            if ($date === null) {
                continue; // §5.3: no provider timestamp, no event (never synthesize)
            }
            if ($count++ >= $limit) {
                break;
            }
            $out[] = [
                'source' => $repo,
                'type' => 'tag',
                'externalId' => 'tag:'.$name,
                'tagName' => $name,
                'title' => 'tag '.$name,
                'url' => "https://github.com/{$repo}/releases/tag/".rawurlencode($name),
                'publishedAt' => $date,
                'body' => '',
                'labels' => [],
                'state' => 'published',
            ];
        }

        return $out;
    }

    /** The provider timestamp of a tag node: annotated `tagger.date`, else `committedDate`. */
    public static function dateOf(array $node): ?string
    {
        $raw = $node['target']['tagger']['date'] ?? $node['target']['committedDate'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    /** @return array<string,mixed>|null */
    private static function fetchViaCurl(string $owner, string $name, int $limit, ?string $token, bool $verbose): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        $ch = curl_init('https://api.github.com/graphql');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Content-Type: application/json',
                'User-Agent: php-arazzo-ecosystem-feed',
                'Authorization: Bearer '.$token,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'query' => self::query(),
                'variables' => ['owner' => $owner, 'name' => $name, 'first' => $limit],
            ]),
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($resp) || $code < 200 || $code >= 300) {
            if ($verbose) {
                fwrite(STDERR, "  [warn] graphql tags {$owner}/{$name} HTTP {$code}\n");
            }

            return null;
        }
        $decoded = json_decode($resp, true);

        return is_array($decoded) ? $decoded : null;
    }
}
