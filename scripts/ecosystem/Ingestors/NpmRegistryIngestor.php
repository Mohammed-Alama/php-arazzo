<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

final class NpmRegistryIngestor
{
    /**
     * Whether `$latest` is strictly newer than the stored `$watermark` (a regression
     * in `dist-tags.latest` must never re-announce an older version).
     */
    public static function shouldEmit(string $latest, ?string $watermark): bool
    {
        if ($watermark === null || $watermark === '') {
            return true;
        }

        return version_compare($latest, $watermark, '>');
    }

    /**
     * @param  ?string  $watermark  newest version seen before (§5.4); only strictly newer versions emit
     * @return array<int,array<string,mixed>>
     */
    public static function poll(string $package, string $sourceId, ?string $watermark = null): array
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

        $latest = $data['dist-tags']['latest'] ?? null;
        if (!is_string($latest) || $latest === '') {
            return [];
        }

        // npm watermark (§5.4): never emit a version at or behind the stored mark, so a
        // regressed `dist-tags.latest` cannot re-announce. T6 owns the full `time`-map ingest.
        if (!self::shouldEmit($latest, $watermark)) {
            return [];
        }

        $versionData = $data['versions'][$latest] ?? [];
        $time = $data['time'][$latest] ?? null;
        if (!is_string($time) || $time === '') {
            return []; // no provider timestamp: never synthesize one from the run clock
        }
        $ts = strtotime($time);
        if ($ts === false) {
            return [];
        }

        return [[
            'source' => $sourceId,
            'type' => 'release',
            'externalId' => 'npm:'.$latest,
            'title' => $package.'@'.$latest,
            'url' => 'https://www.npmjs.com/package/'.$package.'/v/'.$latest,
            'publishedAt' => gmdate('Y-m-d\TH:i:s\Z', $ts),
            'body' => $versionData['description'] ?? '',
            'labels' => [],
        ]];
    }
}
