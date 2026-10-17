<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Shared pure primitives for the ecosystem pipeline (§5.6, review P2-6).
 *
 * These helpers were copy-pasted across {@see Differ}, {@see Scorer}, {@see TopicMatcher},
 * {@see Triage}, {@see Deliverer}, {@see Renderer}, {@see Watchlist} and
 * {@see Ingestors\SpecBranchIngestor}. One implementation means the pieces cannot drift —
 * in particular PR state and version parsing must give a single answer everywhere.
 */
final class Support
{
    /**
     * Deduplicate a list of scalars, preserving author order.
     *
     * @return string[]
     */
    public static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (is_scalar($value)) {
                $string = (string) $value;
                if ($string !== '') {
                    $list[] = $string;
                }
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * Deduplicate **and sort** a list of scalars, for deterministic fingerprints and
     * watermarks (ordering carries no meaning for these callers).
     *
     * @return string[]
     */
    public static function sortedStringList(mixed $values): array
    {
        $list = self::stringList($values);
        sort($list, SORT_STRING);

        return $list;
    }

    /**
     * Sum an integer field across rows (file stats, diffs).
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    public static function sumField(array $rows, string $field): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) ($row[$field] ?? 0);
        }

        return $total;
    }

    /**
     * Derive the PR lifecycle state (§5.3: open/draft/ready/merged/closed).
     *
     * The single implementation shared by the {@see Differ} PR descriptor (which has the
     * previous fingerprint and can therefore report `ready`) and {@see Watchlist::displayState()}
     * (which does not, and therefore never reports `ready`).
     *
     * @param  array<string,mixed>  $obs
     * @param  array<string,mixed>  $previousFields
     */
    public static function prState(array $obs, array $previousFields = []): string
    {
        if (($obs['merged'] ?? false) === true) {
            return 'merged';
        }
        if (($obs['state'] ?? '') === 'closed') {
            return 'closed';
        }
        if (($obs['draft'] ?? false) === true) {
            return 'draft';
        }
        if (($previousFields['state'] ?? null) === 'draft') {
            return 'ready';
        }

        return 'open';
    }

    /** Normalized version of a release/tag/npm id: `release:@scope/pkg@1.2.0` -> `1.2.0`. */
    public static function versionOf(string $externalId): string
    {
        $raw = $externalId;
        foreach (['release:', 'tag:', 'npm:'] as $prefix) {
            if (str_starts_with($raw, $prefix)) {
                $raw = substr($raw, strlen($prefix));

                break;
            }
        }

        [, $version] = self::parseTagName($raw);

        return $version;
    }

    /** Raw tag name behind a release/tag id, exactly as GitHub reports it. */
    public static function rawTagName(string $externalId): string
    {
        foreach (['release:', 'tag:'] as $prefix) {
            if (str_starts_with($externalId, $prefix)) {
                return substr($externalId, strlen($prefix));
            }
        }

        return '';
    }

    /**
     * Split a tag name into `[package, version]`. Handles monorepo tags
     * (`@redocly/cli@2.64.0`) and plain tags (`v1.0.0`).
     *
     * @return array{0:string,1:string}
     */
    public static function parseTagName(string $tagName): array
    {
        $tagName = trim($tagName);
        if ($tagName === '') {
            return ['', ''];
        }

        $at = strrpos($tagName, '@');
        if ($at !== false && $at > 0) {
            $package = substr($tagName, 0, $at);
            $version = substr($tagName, $at + 1);
            if (preg_match('/^v?\d/', $version) === 1) {
                return [$package, (string) preg_replace('/^v(?=\d)/', '', $version)];
            }
        }

        return ['', (string) preg_replace('/^v(?=\d)/', '', $tagName)];
    }

    /** A `x.0.0` (or `vx.0.0`) semver major. */
    public static function isMajor(string $version): bool
    {
        return preg_match('/^v?(\d+)\.0\.0/', $version) === 1;
    }

    /** A `x.y.0` (or `vx.y.0`) semver major or minor. */
    public static function isMajorOrMinor(string $version): bool
    {
        return preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', $version, $m) === 1 && $m[3] === '0';
    }

    /** A version carrying a `-prerelease` / `-rc` suffix. */
    public static function isPrerelease(string $version): bool
    {
        return str_contains($version, '-');
    }
}
