<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Interfaces\EventLedgerInterface;

final class RecordingEventLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string, payload: array}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType, 'payload' => $payload];
    }

    /** @return list<string> */
    public function eventTypes(): array
    {
        return array_column($this->appended, 'eventType');
    }
}
