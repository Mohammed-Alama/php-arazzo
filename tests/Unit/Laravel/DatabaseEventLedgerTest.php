<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

it('appends an event to the database', function (): void {
    /** @var TestCase $this */
    $builder = $this->createMock(Builder::class);
    $builder->expects($this->once())->method('insert')->willReturn(true);

    $db = $this->createMock(ConnectionInterface::class);
    $db->method('table')->with('arazzo_events')->willReturn($builder);

    $ledger = new DatabaseEventLedger($db, 'arazzo_events');
    $ledger->append('exec_1', 'StepExecuted', ['stepId' => 'A']); // mock's expects($this->once()) is the assertion
});

it('swallows and logs a database failure instead of throwing', function (): void {
    /** @var TestCase $this */
    $builder = $this->createMock(Builder::class);
    $builder->method('insert')->willThrowException(new \RuntimeException('connection refused'));

    $db = $this->createMock(ConnectionInterface::class);
    $db->method('table')->willReturn($builder);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('warning');

    $ledger = new DatabaseEventLedger($db, 'arazzo_events', $logger);

    $ledger->append('exec_1', 'StepExecuted', ['stepId' => 'A']); // must not throw
});
