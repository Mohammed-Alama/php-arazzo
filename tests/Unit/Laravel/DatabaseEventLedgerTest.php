<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\DatabaseEventLedger;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;

class DatabaseEventLedgerTest extends TestCase
{
    public function test_appends_event_to_database(): void
    {
        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())->method('insert')->willReturn(true);

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->with('arazzo_events')->willReturn($builder);

        $ledger = new DatabaseEventLedger($db, 'arazzo_events');
        $ledger->append('wf_1', 'StepExecuted', ['stepId' => 'A']);
    }
}
