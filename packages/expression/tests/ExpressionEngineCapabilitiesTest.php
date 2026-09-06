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
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;

function capabilityStep(?RequestBody $body = null, array $criteria = [], array $outputs = []): Step
{
    return new Step(
        stepId: 's1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: $body,
        successCriteria: $criteria,
        onSuccess: [],
        onFailure: [],
        outputs: $outputs,
    );
}

function capabilityDocument(): ArazzoDocument
{
    $step = new Step(
        stepId: 'fetch',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['user' => new Expression('{$response.body}')],
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

it('parses valid expressions without an error', function (): void {
    $engine = new ExpressionEngine();

    expect($engine->parseExpression('{$inputs.name}'))->toBeNull();
    expect($engine->parseExpression('{$steps.s1.outputs.ok#/value}'))->toBeNull();
});

it('returns a syntax error for unparseable expressions', function (): void {
    $engine = new ExpressionEngine();

    $error = $engine->parseExpression('{$inputs}');
    expect($error)->toBeInstanceOf(ExpressionSyntaxException::class);
    expect($error->getMessage())->not->toBeEmpty();
});

it('projects every reference kind without leaking the AST', function (
    string $raw,
    ReferenceKind $kind,
    ?string $target,
    ?string $part,
    ?string $name,
    ?string $httpPart,
    ?string $pointer,
): void {
    $ref = (new ExpressionEngine())->expressionReferences($raw);

    expect($ref)->toBeInstanceOf(ExpressionReference::class)
        ->and($ref->kind)->toBe($kind)
        ->and($ref->target)->toBe($target)
        ->and($ref->part)->toBe($part)
        ->and($ref->name)->toBe($name)
        ->and($ref->httpPart)->toBe($httpPart)
        ->and($ref->jsonPointer)->toBe($pointer);
})->with([
    'input' => ['{$inputs.foo}', ReferenceKind::Input, 'foo', null, null, null, null],
    'output root' => ['{$outputs.out}', ReferenceKind::Output, 'out', null, null, null, null],
    'step output' => ['{$steps.s1.outputs.ok}', ReferenceKind::Step, 's1', 'outputs', 'ok', null, null],
    'step output pointer' => ['{$steps.s1.outputs.ok#/x}', ReferenceKind::Step, 's1', 'outputs', 'ok', null, '/x'],
    'step request bare' => ['{$steps.s1.request}', ReferenceKind::Step, 's1', 'request', null, null, null],
    'step request body pointer' => ['{$steps.s1.request.body#/a/b}', ReferenceKind::Step, 's1', 'request', null, 'body', '/a/b'],
    'step response header' => ['{$steps.s1.response.header.Authorization}', ReferenceKind::Step, 's1', 'response', 'Authorization', 'header', null],
    'step output shortcut' => ['{$steps.s1.ok}', ReferenceKind::Step, 's1', 'outputs', 'ok', null, null],
    'implicit request' => ['{$request.body}', ReferenceKind::Step, null, 'request', null, 'body', null],
    'implicit response meta' => ['{$response.statusCode}', ReferenceKind::Step, null, 'response', null, 'statusCode', null],
    'workflow' => ['{$workflows.wf1.outputs.x}', ReferenceKind::Workflow, 'wf1', 'outputs', 'x', null, null],
    'source' => ['{$sourceDescriptions.api.url}', ReferenceKind::Source, 'api', null, 'url', null, null],
    'component' => ['{$components.parameters.p1}', ReferenceKind::Component, 'parameters', null, 'p1', null, null],
    'message header' => ['{$message.header.Authorization}', ReferenceKind::Message, null, 'header', 'Authorization', null, null],
    'message payload pointer' => ['{$message.payload#/a}', ReferenceKind::Message, null, 'payload', null, null, '/a'],
    'self' => ['{$self}', ReferenceKind::Self, null, null, null, null, null],
    'http url' => ['{$url}', ReferenceKind::HttpMeta, null, null, null, 'url', null],
    'http method' => ['{$method}', ReferenceKind::HttpMeta, null, null, null, 'method', null],
    'http status code' => ['{$statusCode}', ReferenceKind::HttpMeta, null, null, null, 'statusCode', null],
]);

it('returns null references for unparseable expressions', function (): void {
    expect((new ExpressionEngine())->expressionReferences('{$garbage}'))->toBeNull();
});

it('builds the symbol table over a document', function (): void {
    $table = (new ExpressionEngine())->buildSymbolTable(capabilityDocument());

    expect($table->workflows)->toHaveKey('main')
        ->and($table->sourceDescriptions)->toHaveKey('api')
        ->and($table->components['parameters'])->toHaveKey('p1');
});

it('evaluates success criteria and criteria lists against the context', function (): void {
    $engine = new ExpressionEngine();
    $step = capabilityStep(criteria: [new SuccessCriterion(null, '{$statusCode} == 200', CriterionType::Simple)]);
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('s1', [])
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => []]);

    expect($engine->evaluateSuccessCriteria($step, $context))->toBeTrue();
    expect($engine->evaluateCriteria([new SuccessCriterion(null, '{$statusCode} == 201', CriterionType::Simple)], $step, $context))->toBeFalse();
});

it('evaluates selectors rooted at the current step', function (): void {
    $engine = new ExpressionEngine();
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('s1', [])
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => ['users' => [['id' => 1], ['id' => 2]]]]);

    $selector = new Selector(null, '$.users[0].id', ExpressionType::JsonPath);

    expect($engine->evaluateSelector($selector, $context, 's1'))->toBe(1);
});

it('queries xpath and reports the supported versions', function (): void {
    $engine = new ExpressionEngine();

    expect($engine->supportedXPathVersions())->toBe(['xpath-10']);
    expect($engine->queryXPath('<root><user><name>Ada</name></user></root>', '/root/user/name', 'xpath-10'))->toBe('Ada');
});

it('interpolates expression references into strings', function (): void {
    $engine = new ExpressionEngine();
    $context = (new WorkflowContext('def_1'))->withInput('name', 'Ada');

    expect($engine->interpolate('Hello {$inputs.name}!', $context, 's1'))->toBe('Hello Ada!');
});

it('applies pointer payload replacements and delegates selector targets', function (): void {
    $engine = new ExpressionEngine();
    $pointerStep = capabilityStep(body: new RequestBody(null, null, [new PayloadReplacement('/a/b', 'set')]));
    $selectorStep = capabilityStep(body: new RequestBody(null, null, [new PayloadReplacement('$.user.name', 'Ada', 'jsonpath')]));

    $byPointer = $engine->replacePayload($pointerStep, ['a' => ['b' => 'old']]);
    $bySelector = $engine->replacePayload($selectorStep, ['user' => ['name' => 'old']]);

    expect($byPointer['a']['b'])->toBe('set');
    // Selector-target writes follow PayloadReplacer semantics (scalar leaves are left untouched).
    expect($bySelector)->toBe(['user' => ['name' => 'old']]);
});

it('evaluates jsonpath and json pointer directly', function (): void {
    $engine = new ExpressionEngine();

    expect($engine->jsonPath('$.users[*].id', ['users' => [['id' => 1], ['id' => 2]]]))->toBe([1, 2]);
    expect($engine->jsonPointer(['a' => ['b' => 5]], '/a/b'))->toBe(5);
    expect($engine->jsonPointer(['a' => 1], '/missing'))->toBeNull();
});
