<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

final class NpmRegistryIngestor
{
    /** @return array<int,array<string,mixed>> */
    public static function poll(string $package, string $sourceId): array
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
        if ($latest === null) {
            return [];
        }
        $versionData = $data['versions'][$latest] ?? [];
        $time = $data['time'][$latest] ?? gmdate('c');

        return [[
            'source' => $sourceId,
            'type' => 'release',
            'externalId' => 'npm:'.$latest,
            'title' => $package.'@'.$latest,
            'url' => 'https://www.npmjs.com/package/'.$package.'/v/'.$latest,
            'publishedAt' => date('c', strtotime($time) ?: time()),
            'body' => $versionData['description'] ?? '',
            'labels' => [],
        ]];
    }
}
