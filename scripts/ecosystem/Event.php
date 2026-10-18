<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Event-model v2 (§5.3): an event is a *transition*, never an observation.
 *
 * `occurredAt` is always a provider timestamp; `observedAt` is when this run saw it.
 * The id is derived from `(entityId, kind, toValue)` so replaying the same transition
 * yields the same event (idempotent, lossless `events/YYYY-MM.jsonl`).
 */
final class Event
{
    /**
     * @param  array{type:string,title:string,url:string}  $subject
     * @param  array<string,mixed>  $change
     * @param  string[]  $topics
     * @param  string[]  $roadmapRefs
     * @param  string[]  $why
     */
    public function __construct(
        public readonly string $id,
        public readonly string $entityId,
        public readonly string $kind,
        public readonly string $source,
        public readonly string $occurredAt,
        public readonly string $observedAt,
        public readonly array $subject,
        public readonly array $change,
        public readonly array $topics = [],
        public readonly array $roadmapRefs = [],
        public readonly ?string $tier = null,
        public readonly ?int $score = null,
        public readonly array $why = [],
        public readonly bool $breaking = false,
    ) {}

    public static function makeId(string $entityId, string $kind, string $toValue): string
    {
        return 'evt_'.substr(hash('sha256', $entityId.'|'.$kind.'|'.$toValue), 0, 16);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entityId' => $this->entityId,
            'kind' => $this->kind,
            'source' => $this->source,
            'occurredAt' => $this->occurredAt,
            'observedAt' => $this->observedAt,
            'subject' => $this->subject,
            'change' => $this->change,
            'topics' => $this->topics,
            'roadmapRefs' => $this->roadmapRefs,
            'tier' => $this->tier,
            'score' => $this->score,
            'why' => $this->why,
            'breaking' => $this->breaking,
        ];
    }
}
