<?php

declare(strict_types=1);

namespace Alama\Arazzo\Console\Cli;

use Alama\Arazzo\Interfaces\EventLedgerInterface;

/**
 * Ledger sink for CLI/single-process runs that do not want durable event
 * history. Accepts everything, records nothing.
 */
final class NullEventLedger implements EventLedgerInterface
{
    public function append(string $executionId, string $eventType, array $data = []): void {}
}
