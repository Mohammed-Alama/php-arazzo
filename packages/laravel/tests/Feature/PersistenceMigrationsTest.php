<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the arazzo_definitions table with expected columns', function (): void {
    expect(Schema::hasTable('arazzo_definitions'))->toBeTrue();
    expect(Schema::hasColumns('arazzo_definitions', [
        'id', 'document_identity', 'content_hash', 'raw_document', 'created_at',
    ]))->toBeTrue();
});

it('creates the arazzo_executions table with expected columns', function (): void {
    expect(Schema::hasTable('arazzo_executions'))->toBeTrue();
    expect(Schema::hasColumns('arazzo_executions', [
        'id', 'definition_id', 'workflow_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('creates the arazzo_events table with expected columns', function (): void {
    expect(Schema::hasTable('arazzo_events'))->toBeTrue();
    expect(Schema::hasColumns('arazzo_events', [
        'id', 'execution_id', 'event_type', 'payload', 'created_at',
    ]))->toBeTrue();
});

it('creates the arazzo_pending_correlations table with expected columns', function (): void {
    expect(Schema::hasTable('arazzo_pending_correlations'))->toBeTrue();
    expect(Schema::hasColumns('arazzo_pending_correlations', [
        'id', 'correlation_id', 'execution_id', 'step_id', 'channel_path', 'expires_at', 'created_at',
    ]))->toBeTrue();
});

it('enforces the unique index on definitions and rejects duplicate content', function (): void {
    DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'document_identity' => 'Test Doc',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => json_encode(['x' => 1]),
        'created_at' => now(),
    ]);

    expect(fn () => DB::table('arazzo_definitions')->insert([
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        'document_identity' => 'Test Doc',
        'content_hash' => str_repeat('a', 64),
        'raw_document' => json_encode(['x' => 2]),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});
