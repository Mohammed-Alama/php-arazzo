<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

final class WebScrapeIngestor
{
    /** @return array<int,array<string,mixed>> single checksum event */
    public static function poll(string $url, string $sourceId, string $kind): array
    {
        $headers = @get_headers($url, true);
        $etag = null;
        $lastMod = null;
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strtolower((string) $k) === 'etag') {
                    $etag = is_array($v) ? end($v) : $v;
                }
                if (strtolower((string) $k) === 'last-modified') {
                    $lastMod = is_array($v) ? end($v) : $v;
                }
            }
        }
        $body = @file_get_contents($url);
        $hash = $body !== false ? hash('sha256', $body) : ($etag ?? md5($url));
        $publishedAt = $lastMod ? date('c', strtotime($lastMod) ?: time()) : gmdate('c');

        return [[
            'source' => $sourceId,
            'type' => $kind,
            'externalId' => 'checksum:' . substr($hash, 0, 12),
            'title' => $sourceId . ' checksum ' . substr($hash, 0, 12),
            'url' => $url,
            'publishedAt' => $publishedAt,
            'body' => 'etag=' . ($etag ?? 'n/a') . ' hash=' . $hash,
            'labels' => [],
        ]];
    }
}
