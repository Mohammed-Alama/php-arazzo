<?php

use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Alama\LaravelArazzo\Execution\Contracts\PendingCorrelationRegistryInterface;
use Alama\LaravelArazzo\Execution\PendingCorrelation;
use Alama\LaravelArazzo\Laravel\DatabasePendingCorrelationRegistry;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->registry = new DatabasePendingCorrelationRegistry(DB::connection());
});

it('creates and finds a pending correlation', function () {
    $this->registry->create('corr_1', 'exec_1', 'step_1', 'channels/rides/created');
    
    $correlation = $this->registry->findByCorrelationId('corr_1');
    
    expect($correlation)->not->toBeNull()
        ->and($correlation->correlationId)->toBe('corr_1')
        ->and($correlation->executionId)->toBe('exec_1')
        ->and($correlation->stepId)->toBe('step_1')
        ->and($correlation->channelPath)->toBe('channels/rides/created');
});

it('returns null for an unknown correlation id', function () {
    expect($this->registry->findByCorrelationId('missing'))->toBeNull();
});

it('consume deletes the row so a second lookup returns null', function () {
    $this->registry->create('corr_2', 'exec_2', 'step_2', 'channel_2');
    $this->registry->consume('corr_2');
    
    expect($this->registry->findByCorrelationId('corr_2'))->toBeNull();
});

it('existsForExecution reflects whether any correlation is outstanding', function () {
    expect($this->registry->existsForExecution('exec_3'))->toBeFalse();
    
    $this->registry->create('corr_3', 'exec_3', 'step_3', 'channel_3');
    expect($this->registry->existsForExecution('exec_3'))->toBeTrue();
    
    $this->registry->consume('corr_3');
    expect($this->registry->existsForExecution('exec_3'))->toBeFalse();
});
