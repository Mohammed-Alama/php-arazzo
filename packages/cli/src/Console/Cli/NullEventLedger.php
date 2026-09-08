<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console\Cli;

use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;

/**
 * Ledger sink for CLI/single-process runs that do not want durable event
 * history. Accepts everything, records nothing.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class NullEventLedger implements EventLedgerInterface
{
    public function append(string $executionId, string $eventType, array $data = []): void {}
}
