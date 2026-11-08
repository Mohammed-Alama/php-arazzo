<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;

it('applies the receive default through the evaluation engine', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $step = StepFactory::async('wait', null, new StepFlow(), new StepIo(), 'receive', 'channels/x');

    $received = (new WorkflowContext('def_1'))->withStepResult('wait', ['messageReceived' => true]);

    expect($engine->evaluateSuccessCriteria($step, $received))->toBeTrue()
        ->and($engine->evaluateSuccessCriteria($step, new WorkflowContext('def_1')))->toBeFalse();
});

it('applies the operation 2xx default through the evaluation engine', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $step = StepFactory::http('op', null, new StepFlow(), new StepIo(), operationId: 'findPet');

    $ok = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 200, 'headers' => [], 'body' => []]);
    $error = (new WorkflowContext('def_1'))->withStepResponse('op', ['statusCode' => 500, 'headers' => [], 'body' => []]);

    expect($engine->evaluateSuccessCriteria($step, $ok))->toBeTrue()
        ->and($engine->evaluateSuccessCriteria($step, $error))->toBeFalse();
});
