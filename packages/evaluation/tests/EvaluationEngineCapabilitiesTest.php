<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;

function capabilityStep(?RequestBody $body = null, array $criteria = [], array $outputs = []): Step
{
    return new Step(
        stepId: 's1',
        description: null,
        target: new StepTarget(),
        flow: new StepFlow(),
        io: new StepIo(requestBody: $body, successCriteria: $criteria, outputs: $outputs),
    );
}

function capabilityDocument(): ArazzoDocument
{
    $step = StepFactory::http(
        stepId: 'fetch',
        description: null,
        flow: new StepFlow(),
        io: new StepIo(outputs: ['user' => new Expression('{$response.body}')]),
        operationId: 'op',
    );
    $wf = new Workflow(
        workflowId: 'main',
        summary: null,
        description: null,
        inputs: ['type' => 'object', 'properties' => ['userId' => ['type' => 'string']]],
        dependsOn: [],
        steps: [$step],
        successActions: [],
        failureActions: [],
        outputs: [],
        parameters: [],
    );

    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', '/x', SourceType::Openapi)],
        workflows: [$wf],
        components: new Components([], ['p1' => new Parameter('p1', ParameterIn::Query, null)], [], []),
        specificationExtensions: [],
    );
}

it('evaluates success criteria and criteria lists against the context', function (): void {
    $engine = new EvaluationEngine();
    $step = capabilityStep(criteria: [new SuccessCriterion(null, '{$statusCode} == 200', CriterionType::Simple)]);
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('s1', [])
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => []]);

    expect($engine->evaluateSuccessCriteria($step, $context))->toBeTrue();
    expect($engine->evaluateCriteria([new SuccessCriterion(null, '{$statusCode} == 201', CriterionType::Simple)], $step, $context))->toBeFalse();
});

it('evaluates selectors rooted at the current step', function (): void {
    $engine = new EvaluationEngine();
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('s1', [])
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => ['users' => [['id' => 1], ['id' => 2]]]]);

    $selector = new Selector(null, '$.users[0].id', ExpressionType::JsonPath);

    expect($engine->evaluateSelector($selector, $context, 's1'))->toBe(1);
});

it('queries xpath and reports the supported versions', function (): void {
    $engine = new EvaluationEngine();

    expect($engine->supportedXPathVersions())->toBe(['xpath-10']);
    expect($engine->queryXPath('<root><user><name>Ada</name></user></root>', '/root/user/name', 'xpath-10'))->toBe('Ada');
});

it('interpolates expression references into strings', function (): void {
    $engine = new EvaluationEngine();
    $context = (new WorkflowContext('def_1'))->withInput('name', 'Ada');

    expect($engine->interpolate('Hello {$inputs.name}!', $context, 's1'))->toBe('Hello Ada!');
});

it('applies pointer payload replacements and delegates selector targets', function (): void {
    $engine = new EvaluationEngine();
    $pointerStep = capabilityStep(body: new RequestBody(null, null, [new PayloadReplacement('/a/b', 'set')]));
    $selectorStep = capabilityStep(body: new RequestBody(null, null, [new PayloadReplacement('$.user.name', 'Ada', 'jsonpath')]));

    $byPointer = $engine->replacePayload($pointerStep, ['a' => ['b' => 'old']]);
    $bySelector = $engine->replacePayload($selectorStep, ['user' => ['name' => 'old']]);

    expect($byPointer['a']['b'])->toBe('set');
    // Selector-target writes follow PayloadReplacer semantics (scalar leaves are left untouched).
    expect($bySelector)->toBe(['user' => ['name' => 'old']]);
});

it('evaluates jsonpath and json pointer directly', function (): void {
    $engine = new EvaluationEngine();

    expect($engine->jsonPath('$.users[*].id', ['users' => [['id' => 1], ['id' => 2]]]))->toBe([1, 2]);
    expect($engine->jsonPointer(['a' => ['b' => 5]], '/a/b'))->toBe(5);
    expect($engine->jsonPointer(['a' => 1], '/missing'))->toBeNull();
});
