<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Spec\PendingCorrelation;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepOutputExtractor;
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

function expiryResumer(bool $expired): array
{
    [$definitionRegistry, $definitionId] = resumerDocument();

    $stateStore = new ResumerMockStateStore();
    $stateStore->state = [
        'definitionId' => $definitionId,
        'workflowId' => 'wf_1',
        'inputs' => [],
        'steps' => [],
        'components' => [],
    ];

    $ledger = new ResumerMockEventLedger();
    $outcomeHandler = new RecordingStepOutcomeHandler();
    $pendingCorrelations = expiryRegistry($expired);

    $outputExtractor = \Mockery::mock(StepOutputExtractor::class);
    $outputExtractor->shouldReceive('extractOutputs')->andReturn([]);
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluateSuccessCriteria')->andReturn(true);

    $resumer = new CorrelationResumer(
        $pendingCorrelations,
        $stateStore,
        $definitionRegistry,
        $outputExtractor,
        $engine,
        $outcomeHandler,
        $ledger,
        new ResumerMockLockManager(),
    );

    return [$resumer, $pendingCorrelations, $ledger, $outcomeHandler];
}

it('routes an expired receive correlation through the failure path', function (): void {
    [$resumer, $pendingCorrelations, $ledger, $outcomeHandler] = expiryResumer(expired: true);

    // A late webhook arrives after the timeout window.
    $resumer->resume('corr_x', ['statusCode' => 200, 'body' => ['late' => true]]);

    expect($ledger->appended[0]['eventType'] ?? null)->toBe('step.correlation_expired')
        ->and($outcomeHandler->handled)->toHaveCount(1)
        ->and($outcomeHandler->handled[0]['criteriaMet'])->toBeFalse()
        ->and($outcomeHandler->handled[0]['context']->getSteps()['wait-for-ride']['response']['statusCode'])->toBe(504)
        ->and($pendingCorrelations->consumed)->toBe(['corr_x']);
});

it('does not treat a live correlation as expired', function (): void {
    [$resumer, $pendingCorrelations, $ledger, $outcomeHandler] = expiryResumer(expired: false);

    $resumer->resume('corr_x', ['statusCode' => 200, 'body' => ['ok' => true]]);

    expect($ledger->appended[0]['eventType'] ?? null)->toBe('step.resumed')
        ->and($outcomeHandler->handled)->toHaveCount(1)
        ->and($outcomeHandler->handled[0]['criteriaMet'])->toBeTrue()
        ->and($outcomeHandler->handled[0]['context']->getSteps()['wait-for-ride']['response']['statusCode'])->toBe(200)
        ->and($pendingCorrelations->consumed)->toBe(['corr_x']);
});
