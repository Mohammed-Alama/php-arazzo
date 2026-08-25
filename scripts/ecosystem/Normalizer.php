<?php

declare(strict_types=1);

namespace Ecosystem;

final class Normalizer
{
    /**
     * Map raw item from an ingestor to a FeedEvent.
     * Heuristics: label/title/url -> tags, severity from type/state.
     *
     * @param array<string,mixed> $raw must contain at least source/type/externalId/title/url/publishedAt
     */
    public static function normalize(array $raw): FeedEvent
    {
        $source = (string) ($raw['source'] ?? 'unknown');
        $externalId = (string) ($raw['externalId'] ?? $raw['url'] ?? $raw['title'] ?? uniqid('', true));
        $id = FeedEvent::makeId($source, $externalId);
        $type = (string) ($raw['type'] ?? 'unknown');
        $title = (string) ($raw['title'] ?? $externalId);
        $url = (string) ($raw['url'] ?? '');
        $publishedAt = (string) ($raw['publishedAt'] ?? gmdate('c'));

        $haystack = strtolower(implode(' ', [
            $title,
            $raw['body'] ?? '',
            implode(' ', $raw['labels'] ?? []),
            $url,
        ]));

        $tags = [];

        $keywordMap = [
            'soap' => ['soap', 'wsdl'],
            'wsdl' => ['wsdl'],
            'xml' => ['xml', 'xpath', 'application/xml'],
            'xpath' => ['xpath'],
            'mcp' => ['mcp', 'model context protocol', 'mcp server', 'mcp compiler'],
            'cli' => ['cli ', 'command line', 'respect-cli', 'arazzo-cli'],
            'actor' => ['actor', 'human-in-loop', 'human in the loop', 'human-in-the-loop'],
            'human' => ['human'],
            'loop' => ['loop', 'iteration', 'goto loop'],
            'a2a' => ['a2a', 'agent2agent', 'agent-to-agent'],
            'grpc' => ['grpc'],
            'graphql' => ['graphql'],
            'transformer' => ['transformer'],
            'function' => ['function support'],
            'breaking' => ['breaking', '2.0', 'major'],
            'schema' => ['schema', 'json schema'],
            'spec' => ['spec', 'arazzo', 'openapi'],
        ];

        foreach ($keywordMap as $tag => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($haystack, $kw)) {
                    $tags[] = $tag;

                    break;
                }
            }
        }

        $tags = array_values(array_unique($tags));

        // severity heuristics
        $severity = 'watch';
        if (in_array($type, ['release', 'tag'], true)) {
            $severity = 'actionable';
        }
        if ($type === 'pr' && (($raw['state'] ?? '') === 'merged' || ($raw['merged'] ?? false) === true)) {
            $severity = 'actionable';
        }
        if (in_array('breaking', $tags, true) || str_contains($haystack, 'breaking change')) {
            $severity = 'breaking';
        }
        if (in_array($source, ['OAI/Arazzo-Specification'], true) && $type === 'release') {
            $severity = 'breaking'; // spec releases need review
        }

        $relevance = RelevanceMapper::map($tags);

        return new FeedEvent($id, $source, $type, $title, $url, $publishedAt, $tags, $severity, $raw, $relevance);
    }

    /**
     * @param array<int,array<string,mixed>> $rawItems
     *
     * @return FeedEvent[]
     */
    public static function normalizeMany(array $rawItems): array
    {
        $out = [];
        $seen = [];
        foreach ($rawItems as $raw) {
            $ev = self::normalize($raw);
            if (!isset($seen[$ev->id])) {
                $seen[$ev->id] = true;
                $out[] = $ev;
            }
        }

        return $out;
    }
}
