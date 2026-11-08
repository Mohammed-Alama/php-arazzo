<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\ExpressionEngine;

/**
 * @param  list<SuccessCriterion>  $criteria
 */
function receiveDefaultStep(array $criteria = []): Step
{
    return StepFactory::async(
        'wait',
        null,
        new StepFlow(),
        new StepIo(successCriteria: $criteria),
        'receive',
        'channels/rides/created',
    );
}

/**
 * @param  array<string, mixed>  $record
 */
function receiveDefaultContext(array $record): WorkflowContext
{
    return (new WorkflowContext('def_1'))->withStepResult('wait', $record);
}

function receiveDefaultEvaluator(): CriteriaEvaluator
{
    return new CriteriaEvaluator(new ExpressionEngine());
}

it('passes an empty-criteria receive step when a message arrived', function (): void {
    $context = receiveDefaultContext(['messageReceived' => true]);

    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), $context))->toBeTrue();
});

it('fails an empty-criteria receive step when no record exists', function (): void {
    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), new WorkflowContext('def_1')))->toBeFalse();
});

it('fails an empty-criteria receive step when the marker is false', function (): void {
    $context = receiveDefaultContext(['messageReceived' => false]);

    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), $context))->toBeFalse();
});

it('fails safely when the step record is not an array', function (): void {
    $malformed = new WorkflowContext('def_1', steps: ['wait' => 'not-an-array']);

    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep(), $malformed))->toBeFalse();
});

it('still lets explicit receive criteria decide the outcome', function (): void {
    $fail = new SuccessCriterion(null, '$response.body.ok == false', CriterionType::Simple);
    $context = receiveDefaultContext([
        'messageReceived' => true,
        'response' => ['statusCode' => 200, 'headers' => [], 'body' => ['ok' => true]],
    ]);

    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria(receiveDefaultStep([$fail]), $context))->toBeFalse();
});

it('keeps the operation 2xx default', function (): void {
    $step = StepFactory::http('op', null, new StepFlow(), new StepIo(), operationId: 'findPet');
    $ok = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 200, 'headers' => [], 'body' => []]);
    $error = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 500, 'headers' => [], 'body' => []]);

    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria($step, $ok))->toBeTrue()
        ->and(receiveDefaultEvaluator()->evaluateSuccessCriteria($step, $error))->toBeFalse();
});

it('does not apply a default to other empty-criteria targets', function (): void {
    $step = StepFactory::workflow('child', null, new StepFlow(), new StepIo(), 'child_workflow');

    expect(receiveDefaultEvaluator()->evaluateSuccessCriteria($step, new WorkflowContext('def_1')))->toBeTrue();
});
