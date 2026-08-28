<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\PendingCorrelation;
use Alama\Arazzo\Execution\CorrelationResumer;
use DateTimeImmutable;

require_once __DIR__.'/CorrelationResumerTest.php';

function expiryRegistry(bool $expired): ResumerMockPendingCorrelations
{
    $registry = new ResumerMockPendingCorrelations();
    $registry->toReturn = new PendingCorrelation(
        'corr_x',
        'exec_1',
        'wait-for-ride',
        'channels/rides/created',
        new DateTimeImmutable($expired ? '-10 minutes' : '+10 minutes'),
    );

    return $registry;
}

it('routes an expired receive correlation through the failure path', function (): void {
    $pendingCorrelations = expiryRegistry(expired: true);
    [$definitionRegistry, $definitionId] = \Tests\Execution\resumerDocument();
    $stateStore = new ResumerMockStateStore();

    // Seed state so resume proceeds past the state-missing guard.
    $stateStore->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'inputs' => [],
        'steps' => [],
        'components' => [],
    ];

    $ledger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();
    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, $ledger, new ResumerMockLockManager());

    // A late webhook arrives after the timeout window.
    $resumer->resume('corr_x', ['statusCode' => 200, 'body' => ['late' => true]]);

    expect($ledger->appended)->not->toBeEmpty();

    expect($outcomeHandler->calls)->toHaveCount(1)
        ->and($outcomeHandler->calls[0]['criteriaMet'])->toBeFalse()
        ->and($outcomeHandler->calls[0]['context']->getSteps()['wait-for-ride']['response']['statusCode'])->toBe(504)
        ->and($pendingCorrelations->consumed)->toBe(['corr_x']);
});

it('does not treat a live correlation as expired', function (): void {
    $pendingCorrelations = expiryRegistry(expired: false);
    [$definitionRegistry, $definitionId] = \Tests\Execution\resumerDocument();
    $stateStore = new ResumerMockStateStore();
    $stateStore->preloaded['exec_1'] = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'inputs' => [],
        'steps' => [],
        'components' => [],
    ];

    $ledger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();
    $resumer = new CorrelationResumer($pendingCorrelations, $stateStore, $definitionRegistry, new ResumerMockExpressionResolver(), $outcomeHandler, $ledger, new ResumerMockLockManager());

    $resumer->resume('corr_x', ['statusCode' => 200, 'body' => ['ok' => true]]);

    expect($ledger->appended[0]['eventType'] ?? null)->toBe('step.resumed')
        ->and($outcomeHandler->calls)->toHaveCount(1)
        ->and($outcomeHandler->calls[0]['criteriaMet'])->toBeTrue()
        ->and($outcomeHandler->calls[0]['context']->getSteps()['wait-for-ride']['response']['statusCode'])->toBe(200);
});
