<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

use Ecosystem\GhCli;

require_once dirname(__DIR__).'/GhCli.php';

/**
 * Spec-text diffing against `OAI/Arazzo-Specification` branches (§5.4, ticket #183).
 *
 * Stores `headSha` per branch; on the next run calls the GitHub `compare` API from the
 * stored sha to the branch head and emits two kinds of observations:
 *
 *  - `spec`   → prose spec text changed under the confirmed real paths
 *               (`versions/*.md`, `examples/**`).
 *  - `schema` → JSON-Schema iterations changed (`_archive_/schemas/**`, `**\/schema.yaml`).
 *
 * Only *confirmed* paths are listed — the spec repo has no `src/`, so `src/arazzo.md`
 * and `src/schemas/validation/schema.yaml` never appear (F8 / Appendix D).
 * `schema` observations carry a cheap iteration checksum as a secondary signal.
 */
final class SpecBranchIngestor
{
    /** Schema-iteration paths (JSON Schema / validation schema), not prose spec text. */
    public const SCHEMA_PATTERNS = [
        '_archive_/schemas/**',
        '**/schema.yaml',
        '**/schema.yml',
        '**/schema.json',
    ];

    /**
     * Poll each configured branch: fetch the current head, compare against the stored sha.
     *
     * @param  string[]  $branches  branch names to track (main, dev, v1.2-dev, v1.1-dev)
     * @param  string[]  $watchFiles  confirmed spec-text globs (versions/*.md, examples/**)
     * @param  array<string,string>  $lastShas  branch => headSha stored from the previous run
     * @param  array<string,string>  $newShas  out: branch => current headSha (always set when known)
     * @return array<int,array<string,mixed>> raw observations (pre-Normalizer)
     */
    public static function poll(
        string $repo,
        string $sourceId,
        array $branches,
        array $watchFiles,
        array $lastShas,
        array &$newShas,
        ?string $token = null,
        bool $verbose = false,
    ): array {
        $heads = self::branchHeads($repo, $branches, $token, $verbose);

        $out = [];
        foreach ($branches as $branch) {
            $head = $heads[$branch] ?? '';
            if ($head === '') {
                continue; // branch missing/unreadable — do not lose the stored sha
            }
            $newShas[$branch] = $head;

            $last = (string) ($lastShas[$branch] ?? '');
            if ($last === '' || $last === $head) {
                continue; // baseline (no prior sha) or the branch has not moved
            }

            $compare = self::api("repos/{$repo}/compare/{$last}...{$branch}?per_page=100", $token, $verbose);
            if ($compare === null) {
                if ($verbose) {
                    fwrite(STDERR, "  [warn] {$repo} compare {$last}...{$branch} failed\n");
                }

                continue;
            }

            foreach (self::observations($sourceId, $branch, $last, $head, $watchFiles, $compare) as $observation) {
                $out[] = $observation;
            }
        }

        return $out;
    }

    /**
     * Turn a decoded `compare` payload into spec/schema observations (pure, offline-testable).
     *
     * @param  string[]  $watchFiles  confirmed spec-text globs
     * @param  array<string,mixed>  $compare  decoded GitHub compare response
     * @return array<int,array<string,mixed>>
     */
    public static function observations(
        string $sourceId,
        string $branch,
        string $lastSha,
        string $headSha,
        array $watchFiles,
        array $compare,
    ): array {
        $commitsRaw = is_array($compare['commits'] ?? null) ? $compare['commits'] : [];
        $filesRaw = is_array($compare['files'] ?? null) ? $compare['files'] : [];

        $occurredAt = self::latestCommitDate($commitsRaw);
        if ($occurredAt === '') {
            return []; // §5.3: no event is created without a provider timestamp
        }

        $commits = self::commitTitles($commitsRaw);

        $specRows = [];
        $schemaRows = [];
        foreach ($filesRaw as $file) {
            if (!is_array($file)) {
                continue;
            }
            $path = (string) ($file['filename'] ?? '');
            if ($path === '') {
                continue;
            }
            $row = [
                'path' => $path,
                'status' => (string) ($file['status'] ?? 'modified'),
                'additions' => (int) ($file['additions'] ?? 0),
                'deletions' => (int) ($file['deletions'] ?? 0),
                'changes' => (int) ($file['changes'] ?? 0),
            ];
            if (self::isSchemaPath($path)) {
                $schemaRows[] = $row;
            } elseif (self::matchesAny($path, $watchFiles)) {
                $specRows[] = $row;
            }
        }
        usort($specRows, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
        usort($schemaRows, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        $url = "https://github.com/{$sourceId}/compare/{$lastSha}...{$branch}";
        $body = implode("\n", $commits);

        $out = [];
        if ($specRows !== []) {
            $out[] = [
                'source' => $sourceId,
                'type' => 'spec',
                'externalId' => 'spec:'.$branch,
                'title' => sprintf('Spec %s: %d spec file(s) changed', $branch, count($specRows)),
                'url' => $url,
                'publishedAt' => $occurredAt,
                'body' => $body,
                'labels' => [],
                'branch' => $branch,
                'headSha' => $headSha,
                'files' => array_values(array_map(static fn (array $r): string => (string) $r['path'], $specRows)),
                'fileStats' => $specRows,
                'commits' => $commits,
                'additions' => self::sum($specRows, 'additions'),
                'deletions' => self::sum($specRows, 'deletions'),
            ];
        }

        if ($schemaRows !== []) {
            $out[] = [
                'source' => $sourceId,
                'type' => 'schema',
                'externalId' => 'schema:'.$branch,
                'title' => sprintf('Schema %s: %d schema file(s) changed', $branch, count($schemaRows)),
                'url' => $url,
                'publishedAt' => $occurredAt,
                'body' => $body,
                'labels' => [],
                'branch' => $branch,
                'hash' => self::schemaChecksum($branch, $headSha, $schemaRows),
                'files' => array_values(array_map(static fn (array $r): string => (string) $r['path'], $schemaRows)),
                'fileStats' => $schemaRows,
                'commits' => $commits,
                'additions' => self::sum($schemaRows, 'additions'),
                'deletions' => self::sum($schemaRows, 'deletions'),
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------- network

    /**
     * @param  string[]  $branches
     * @return array<string,string> branch => headSha
     */
    private static function branchHeads(string $repo, array $branches, ?string $token, bool $verbose): array
    {
        $heads = [];
        $list = self::api("repos/{$repo}/branches?per_page=100", $token, $verbose);
        if (is_array($list)) {
            foreach ($list as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $name = (string) ($entry['name'] ?? '');
                $sha = (string) ($entry['commit']['sha'] ?? '');
                if ($name !== '' && $sha !== '') {
                    $heads[$name] = $sha;
                }
            }
        }

        // Fall back to a per-branch ref lookup for any branch not on the first page.
        foreach ($branches as $branch) {
            if (isset($heads[$branch])) {
                continue;
            }
            $ref = self::api('repos/'.$repo.'/git/ref/heads/'.$branch, $token, $verbose);
            $sha = is_array($ref) ? (string) ($ref['object']['sha'] ?? '') : '';
            if ($sha !== '') {
                $heads[$branch] = $sha;
            }
        }

        return $heads;
    }

    /** @return array<string,mixed>|null */
    private static function api(string $endpoint, ?string $token, bool $verbose): ?array
    {
        if (GhCli::isAvailable()) {
            $json = GhCli::api($endpoint, false, $verbose);
            if (is_array($json)) {
                return $json;
            }
        }
        if ($token === null || $token === '') {
            return null;
        }

        $ch = curl_init('https://api.github.com/'.ltrim($endpoint, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: php-arazzo-ecosystem-feed',
                'X-GitHub-Api-Version: 2022-11-28',
                'Authorization: Bearer '.$token,
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code < 200 || $code >= 300) {
            return null;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<int,mixed> $commits */
    private static function latestCommitDate(array $commits): string
    {
        $latest = '';
        foreach ($commits as $commit) {
            if (!is_array($commit)) {
                continue;
            }
            $date = (string) ($commit['commit']['committer']['date'] ?? $commit['commit']['author']['date'] ?? '');
            if ($date !== '' && strcmp($date, $latest) > 0) {
                $latest = $date;
            }
        }

        return $latest;
    }

    /**
     * @param  array<int,mixed>  $commits
     * @return string[]
     */
    private static function commitTitles(array $commits): array
    {
        $titles = [];
        foreach ($commits as $commit) {
            if (!is_array($commit)) {
                continue;
            }
            $message = (string) ($commit['commit']['message'] ?? '');
            $title = trim(explode("\n", $message)[0]);
            if ($title !== '') {
                $titles[] = $title;
            }
        }

        return array_values(array_unique($titles));
    }

    /** A cheap schema-iteration checksum: branch head + the schema files' change shape. */
    private static function schemaChecksum(string $branch, string $headSha, array $rows): string
    {
        return hash('sha256', $branch.'|'.$headSha.'|'.json_encode($rows, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private static function sum(array $rows, string $field): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) ($row[$field] ?? 0);
        }

        return $total;
    }

    private static function isSchemaPath(string $path): bool
    {
        return self::matchesAny($path, self::SCHEMA_PATTERNS);
    }

    /**
     * @param  string[]  $patterns
     */
    private static function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match(self::globToRegex($pattern), $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Glob → anchored regex. `**` spans path separators, `*` does not, `?` is one non-slash
     * character. Used so the watch paths match exactly (`versions/*.md` never matches nested).
     */
    private static function globToRegex(string $glob): string
    {
        $regex = '';
        $length = strlen($glob);
        for ($i = 0; $i < $length; $i++) {
            $char = $glob[$i];
            if ($char === '*') {
                if ($i + 1 < $length && $glob[$i + 1] === '*') {
                    $i++;
                    if ($i + 1 < $length && $glob[$i + 1] === '/') {
                        $i++;
                        $regex .= '(?:.*/)?';
                    } else {
                        $regex .= '.*';
                    }
                } else {
                    $regex .= '[^/]*';
                }
            } elseif ($char === '?') {
                $regex .= '[^/]';
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        return '#^'.$regex.'$#';
    }
}
