<?php

declare(strict_types=1);

use Alama\Arazzo\Spec\ExecutionStatus;
use Alama\Arazzo\Spec\PendingCorrelation;
use Alama\Arazzo\Tests\Support\FakeHttpClient;
use Alama\Arazzo\Tests\Support\FakeLockManager;
use Alama\Arazzo\Tests\Support\InMemoryPendingCorrelations;
use Alama\Arazzo\Tests\Support\RecordingEventDispatcher;
use Alama\Arazzo\Tests\Support\RecordingEventLedger;
use Alama\Arazzo\Tests\Support\RecordingExecutionRegistry;
use Alama\Arazzo\Tests\Support\RecordingStateStore;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('scripts HTTP responses in FIFO order and records every request', function () {
    $client = new FakeHttpClient();
    $client->enqueue(new Response(201, [], '{"id":1}'));
    $client->enqueue(new Response(204));

    $r1 = $client->sendRequest(new Request('POST', 'https://x.test/a'));
    $r2 = $client->sendRequest(new Request('GET', 'https://x.test/b'));

    expect($r1->getStatusCode())->toBe(201)
        ->and($r2->getStatusCode())->toBe(204)
        ->and(array_map(fn ($r) => (string) $r->getUri(), $client->requests))->toBe([
            'https://x.test/a', 'https://x.test/b',
        ]);
});

it('falls back to the default response when nothing is scripted', function () {
    $client = new FakeHttpClient();

    $response = $client->sendRequest(new Request('GET', 'https://x.test/'));

    expect($response->getStatusCode())->toBe(200);
});

it('throws configured transport failures', function () {
    $client = new FakeHttpClient();
    $client->failWith(new RuntimeException('connection refused'));

    $client->sendRequest(new Request('GET', 'https://x.test/'));
})->throws(RuntimeException::class, 'connection refused');

it('runs lock callbacks exactly once per acquisition', function () {
    $lock = new FakeLockManager();
    $runs = 0;

    $result = $lock->acquire('exec_1', 30, function () use (&$runs) {
        return ++$runs;
    });

    expect($result)->toBe(1)
        ->and($lock->acquisitions)->toBe(1)
        ->and($runs)->toBe(1);
});

it('records state saves and serves preloaded state on load', function () {
    $store = new RecordingStateStore();

    expect($store->load('exec_1'))->toBeNull();

    $store->preload('exec_1', ['steps' => ['A' => ['status' => 'success']]]);
    $store->save('exec_1', ['steps' => ['A' => ['status' => 'failure']]], 300);

    expect($store->saved)->toHaveKey('exec_1')
        ->and($store->load('exec_1'))->toBe(['steps' => ['A' => ['status' => 'success']]]);
});

it('records every ledger append with execution id, type, and payload', function () {
    $ledger = new RecordingEventLedger();
    $ledger->append('exec_1', 'step.started', ['stepId' => 'A']);
    $ledger->append('exec_1', 'step.executed', ['stepId' => 'A']);

    expect($ledger->eventTypes())->toBe(['step.started', 'step.executed'])
        ->and($ledger->appended[0])->toBe([
            'executionId' => 'exec_1',
            'eventType' => 'step.started',
            'payload' => ['stepId' => 'A'],
        ]);
});

it('records registry starts and completions with statuses', function () {
    $registry = new RecordingExecutionRegistry();
    $registry->start('exec_1', 'def_1', 'wf_1');
    $registry->complete('exec_1', ExecutionStatus::Succeeded);

    expect(count($registry->started))->toBe(1)
        ->and($registry->started[0]['workflowId'])->toBe('wf_1')
        ->and($registry->completed[0]['status'])->toBe(ExecutionStatus::Succeeded);
});

it('creates, finds, and consumes pending correlations', function () {
    $registry = new InMemoryPendingCorrelations();
    $registry->create('corr_1', 'exec_1', 'waitForEvent', 'orders/events', 60);

    $found = $registry->findByCorrelationId('corr_1');
    expect($found)->toBeInstanceOf(PendingCorrelation::class)
        ->and($found->executionId)->toBe('exec_1')
        ->and($found->expiresAt)->not->toBeNull()
        ->and($registry->existsForExecution('exec_1'))->toBeTrue();

    $registry->consume('corr_1');

    expect($registry->findByCorrelationId('corr_1'))->toBeNull()
        ->and($registry->existsForExecution('exec_1'))->toBeFalse()
        ->and($registry->consumedIds)->toBe(['corr_1']);
});

it('records dispatched events in order', function () {
    $dispatcher = new RecordingEventDispatcher();
    $started = new stdClass();
    $done = new stdClass();

    $dispatcher->dispatch($started);
    $dispatcher->dispatch($done);

    expect($dispatcher->events)->toBe([$started, $done])
        ->and($dispatcher->eventClasses())->toBe([stdClass::class, stdClass::class]);
});
