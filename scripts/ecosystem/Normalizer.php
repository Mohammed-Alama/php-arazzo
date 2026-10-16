<?php

declare(strict_types=1);

namespace Ecosystem;

require_once __DIR__.'/TopicMatcher.php';

final class Normalizer
{
    private static ?TopicMatcher $matcher = null;

    /**
     * Map raw item from an ingestor to a FeedEvent.
     *
     * Topics come from the authored `config/ecosystem/topics.json` vocabulary via
     * {@see TopicMatcher} (§5.6) — word-boundary regex over title + labels (+ first 500 chars of
     * body for non-bot authors), never the URL, never bot bodies (F5). The Act/Watch/Noise tier
     * is derived by {@see Scorer}; `breaking` is never a keyword tag.
     *
     * @param  array<string,mixed>  $raw  must contain at least source/type/externalId/title/url/publishedAt
     */
    public static function normalize(array $raw, ?TopicMatcher $matcher = null): FeedEvent
    {
        $source = (string) ($raw['source'] ?? 'unknown');
        $externalId = (string) ($raw['externalId'] ?? $raw['url'] ?? $raw['title'] ?? uniqid('', true));
        $id = FeedEvent::makeId($source, $externalId);
        $type = (string) ($raw['type'] ?? 'unknown');
        $title = (string) ($raw['title'] ?? $externalId);
        $url = (string) ($raw['url'] ?? '');
        $publishedAt = (string) ($raw['publishedAt'] ?? '');

        $matcher ??= self::matcher();
        $topics = $matcher->topics($raw);

        // Severity is a legacy display hint; the Scorer's tier is authoritative. `breaking` is a
        // derived flag on Act events only (`$scorer['breaking']`), never produced here.
        $severity = 'watch';
        if (in_array($type, ['release', 'tag'], true)) {
            $severity = 'actionable';
        }
        if ($type === 'pr' && (($raw['state'] ?? '') === 'merged' || ($raw['merged'] ?? false) === true)) {
            $severity = 'actionable';
        }

        $relevance = RelevanceMapper::map($topics);

        return new FeedEvent($id, $source, $type, $title, $url, $publishedAt, $topics, $severity, $raw, $relevance);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rawItems
     * @return FeedEvent[]
     */
    public static function normalizeMany(array $rawItems): array
    {
        $out = [];
        $seen = [];
        foreach ($rawItems as $raw) {
            $ev = self::normalize($raw);
            if ($ev->publishedAt === '') {
                // No real provider timestamp: never emit an event without one (F2).
                continue;
            }
            if (!isset($seen[$ev->id])) {
                $seen[$ev->id] = true;
                $out[] = $ev;
            }
        }

        return $out;
    }

    /** Inject a topic matcher (tests); `null` restores the default config-loaded matcher. */
    public static function useMatcher(?TopicMatcher $matcher): void
    {
        self::$matcher = $matcher;
    }

    private static function matcher(): TopicMatcher
    {
        return self::$matcher ??= TopicMatcher::fromDefault();
    }
}
