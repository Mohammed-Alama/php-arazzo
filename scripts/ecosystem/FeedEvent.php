<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Unified feed event — mirrors Normalizer schema from plan.
 */
final class FeedEvent
{
    /**
     * @param string[] $tags
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $type,
        public readonly string $title,
        public readonly string $url,
        public readonly string $publishedAt,
        public readonly array $tags,
        public readonly string $severity,
        public readonly array $raw = [],
        public readonly ?string $relevance = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'type' => $this->type,
            'title' => $this->title,
            'url' => $this->url,
            'publishedAt' => $this->publishedAt,
            'tags' => $this->tags,
            'severity' => $this->severity,
            'relevance' => $this->relevance,
        ];
    }

    public static function makeId(string $source, string $externalId): string
    {
        return hash('sha256', $source . '|' . $externalId);
    }
}
