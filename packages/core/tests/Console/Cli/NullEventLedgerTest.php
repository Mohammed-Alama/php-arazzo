<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Console\Cli;

use Alama\Arazzo\Console\Cli\NullEventLedger;
use Alama\Arazzo\Interfaces\EventLedgerInterface;

it('accepts an append without recording or throwing', function (): void {
    $ledger = new NullEventLedger();

    $ledger->append('exec_1', 'step.started', ['stepId' => 's1']);
    $ledger->append('exec_1', 'step.finished', []);

    expect(true)->toBeTrue();
});

it('is an instance of the event ledger contract', function (): void {
    expect(new NullEventLedger())->toBeInstanceOf(EventLedgerInterface::class);
});
