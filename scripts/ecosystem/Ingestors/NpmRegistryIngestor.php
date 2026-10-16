<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

/**
 * npm full-version ingestion (§5.4, ticket #183).
 *
 * The registry's `time` map is authoritative for *when* each version was published, so we emit
 * one observation per version newer than the stored watermark — not just `dist-tags.latest`
 * (F8). The Differ turns each new version into a `package.version` event.
 */
final class NpmRegistryIngestor
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function poll(string $package, string $sourceId, ?string $since = null): array
    {
        $url = 'https://registry.npmjs.org/'.str_replace('/', '%2F', $package);
        $json = @file_get_contents($url);
        if ($json === false) {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        return self::observationsFromRegistry($package, $sourceId, $data, $since);
    }

    /**
     * Turn a decoded registry document into one observation per version newer than `$since`
     * (pure, offline-testable).
     *
     * @param  array<string,mixed>  $data  decoded registry document (needs `time`, `versions`)
     * @param  string|null  $since  watermark timestamp; only versions published strictly after it
     * @return array<int,array<string,mixed>>
     */
    public static function observationsFromRegistry(string $package, string $sourceId, array $data, ?string $since = null): array
    {
        $times = is_array($data['time'] ?? null) ? $data['time'] : null;
        if ($times === null) {
            return self::latestFallback($package, $sourceId, $data);
        }

        $versions = is_array($data['versions'] ?? null) ? $data['versions'] : [];
        $sinceTs = $since !== null ? (strtotime($since) ?: null) : null;

        /** @var array<int,array{version:string,ts:int}> $rows */
        $rows = [];
        foreach ($times as $version => $time) {
            if (!is_string($version) || in_array($version, ['created', 'modified'], true)) {
                continue;
            }
            if (!is_string($time) || $time === '') {
                continue;
            }
            $ts = strtotime($time);
            if ($ts === false) {
                continue;
            }
            if ($sinceTs !== null && $ts <= $sinceTs) {
                continue;
            }
            $rows[] = ['version' => $version, 'ts' => $ts];
        }
        usort($rows, static fn (array $a, array $b): int => $a['ts'] <=> $b['ts']);

        $out = [];
        foreach ($rows as $row) {
            $version = $row['version'];
            $out[] = [
                'source' => $sourceId,
                'type' => 'release',
                'externalId' => 'npm:'.$version,
                'title' => $package.'@'.$version,
                'url' => 'https://www.npmjs.com/package/'.$package.'/v/'.$version,
                'publishedAt' => date('c', $row['ts']),
                'body' => (string) ($versions[$version]['description'] ?? ''),
                'labels' => [],
            ];
        }

        return $out;
    }

    /**
     * Legacy fallback for a registry document without a `time` map: only `dist-tags.latest`.
     *
     * @param  array<string,mixed>  $data
     * @return array<int,array<string,mixed>>
     */
    private static function latestFallback(string $package, string $sourceId, array $data): array
    {
        $latest = $data['dist-tags']['latest'] ?? null;
        if (!is_string($latest) || $latest === '') {
            return [];
        }
        $versionData = is_array($data['versions'] ?? null) ? ($data['versions'][$latest] ?? []) : [];

        return [[
            'source' => $sourceId,
            'type' => 'release',
            'externalId' => 'npm:'.$latest,
            'title' => $package.'@'.$latest,
            'url' => 'https://www.npmjs.com/package/'.$package.'/v/'.$latest,
            'publishedAt' => gmdate('c'),
            'body' => (string) ($versionData['description'] ?? ''),
            'labels' => [],
        ]];
    }
}
