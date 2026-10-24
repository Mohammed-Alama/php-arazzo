<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;

it('evaluates an xpath criterion against an XML response body through its context', function (): void {
    $step = StepFactory::http('x', null, new StepFlow(), new StepIo(successCriteria: [
        new SuccessCriterion('$response.body', '/root/id', CriterionType::XPath, 'xpath-10'),
    ]), operationId: 'op');
    $context = (new WorkflowContext('w'))->withStepResponse('x', [
        'statusCode' => 200,
        'headers' => [],
        'body' => '<root><id>1</id></root>',
    ]);

    expect((new EvaluationEngine(expression: new ExpressionEngine()))->evaluateSuccessCriteria($step, $context))
        ->toBeTrue();
});
