<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

final class AtomRssIngestor
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function poll(string $url, string $sourceId, int $limit = 20): array
    {
        $xml = @file_get_contents($url);
        if ($xml === false || $xml === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $out = [];
        // Atom: feed->entry ; RSS: channel->item
        $entries = [];
        if (isset($doc->entry)) {
            $entries = $doc->entry;
        } elseif (isset($doc->channel->item)) {
            $entries = $doc->channel->item;
        }

        $n = 0;
        foreach ($entries as $e) {
            if ($n++ >= $limit) {
                break;
            }
            $rawTitle = (string) ($e->title ?? 'entry');
            // Sanitize: collapse whitespace, trim — fixes atom entries like "\n        feat(ecosystem): ..."
            $title = trim(preg_replace('/\s+/u', ' ', $rawTitle) ?? $rawTitle);
            $link = '';
            if (isset($e->link)) {
                $attrs = $e->link->attributes();
                $link = (string) ($attrs['href'] ?? $e->link ?? '');
            } else {
                $link = (string) ($e->link ?? '');
            }
            $id = (string) ($e->id ?? $link ?: $title);
            $published = (string) ($e->updated ?? $e->published ?? $e->pubDate ?? gmdate('c'));
            $summary = (string) ($e->summary ?? $e->content ?? $e->description ?? '');

            $out[] = [
                'source' => $sourceId,
                'type' => str_contains($url, 'commits') ? 'commit' : 'article',
                'externalId' => 'atom:' . md5($id),
                'title' => $title,
                'url' => $link,
                'publishedAt' => date('c', strtotime($published) ?: time()),
                'body' => $summary,
                'labels' => [],
            ];
        }

        return $out;
    }
}
