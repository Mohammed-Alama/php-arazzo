<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Alama\Arazzo\Contracts\ExecutionStatus;
use Alama\Arazzo\Laravel\Persistence\DatabaseExecutionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedTestDefinitionRow(): void
{
    DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'document_identity' => 'Test',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => json_encode(['x' => 1]),
        'created_at' => now(),
    ]);
}

it('inserts an execution row on start', function (): void {
    seedTestDefinitionRow();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

    $this->assertDatabaseHas('arazzo_executions', [
        'id' => 'exec_1',
        'definition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'workflow_id' => 'wf_1',
        'status' => 'running',
    ]);
});

it('is idempotent across repeated start() calls', function (): void {
    seedTestDefinitionRow();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

    expect(DB::table('arazzo_executions')->where('id', 'exec_1')->count())->toBe(1);
});

it('marks an execution succeeded and stamps completed_at', function (): void {
    seedTestDefinitionRow();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

    $registry->complete('exec_1', ExecutionStatus::Succeeded);

    $execution = DB::table('arazzo_executions')->where('id', 'exec_1')->first();
    expect($execution->status)->toBe('succeeded')
        ->and($execution->completed_at)->not->toBeNull();
});

it('does not overwrite an already-completed execution', function (): void {
    seedTestDefinitionRow();

    $registry = new DatabaseExecutionRegistry(DB::connection());
    $registry->start('exec_1', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'wf_1');

    $registry->complete('exec_1', ExecutionStatus::Succeeded);
    $registry->complete('exec_1', ExecutionStatus::Failed);

    $execution = DB::table('arazzo_executions')->where('id', 'exec_1')->first();
    expect($execution->status)->toBe('succeeded');
});
