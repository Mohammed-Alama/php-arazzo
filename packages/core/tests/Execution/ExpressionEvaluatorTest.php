<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Execution\Data\WorkflowContext;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\EvaluationContext;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\SourceDescription;

it('evaluates input references', function () {
    $context = new WorkflowContext('def_1', ['userId' => 123]);
    $evaluator = new ExpressionEvaluator();

    $expr = new Expression('{$inputs.userId}');
    expect($evaluator->evaluate($expr, new EvaluationContext($context)))->toBe(123);

    $exprNotFound = new Expression('{$inputs.missing}');
    expect($evaluator->evaluate($exprNotFound, new EvaluationContext($context)))->toBeNull();
});

it('evaluates step output references', function () {
    $context = (new WorkflowContext('def_1'))->withStepOutput('create-user', 'id', 456);
    $evaluator = new ExpressionEvaluator();

    $expr = new Expression('{$steps.create-user.outputs.id}');
    expect($evaluator->evaluate($expr, new EvaluationContext($context)))->toBe(456);
});

it('evaluates request parts using json pointer', function () {
    $context = (new WorkflowContext('def_1'))->withStepRequest('step1', [
        'headers' => ['Authorization' => 'Bearer token'],
        'query' => ['search' => 'test'],
        'path' => ['id' => 789],
        'body' => ['user' => ['name' => 'Alice']],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.request.header.Authorization}'), new EvaluationContext($context)))->toBe('Bearer token');
    expect($evaluator->evaluate(new Expression('{$steps.step1.request.body#/user/name}'), new EvaluationContext($context)))->toBe('Alice');
});

it('evaluates response parts using json pointer', function () {
    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 201,
        'headers' => ['X-RateLimit' => '100'],
        'body' => ['data' => ['items' => [1, 2, 3]]],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.response.statusCode}'), new EvaluationContext($context)))->toBe(201);
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.header.X-RateLimit}'), new EvaluationContext($context)))->toBe('100');
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/data/items/1}'), new EvaluationContext($context)))->toBe(2);
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/missing}'), new EvaluationContext($context)))->toBeNull();
});

it('evaluates json pointer with escaped characters', function () {
    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'body' => [
            'foo~bar' => 'tilde',
            'foo/bar' => 'slash',
        ],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/foo~0bar}'), new EvaluationContext($context)))->toBe('tilde');
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/foo~1bar}'), new EvaluationContext($context)))->toBe('slash');
});

it('evaluates bare HttpMetaRef against the current step when stepId is given', function () {
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step1', ['method' => 'POST', 'url' => 'http://x/y'])
        ->withStepResponse('step1', ['statusCode' => 201]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$statusCode}'), new EvaluationContext($context, 'step1')))->toBe(201);
    expect($evaluator->evaluate(new Expression('{$method}'), new EvaluationContext($context, 'step1')))->toBe('POST');
    expect($evaluator->evaluate(new Expression('{$url}'), new EvaluationContext($context, 'step1')))->toBe('http://x/y');
});

it('returns null for HttpMetaRef when no current step is given', function () {
    $context = new WorkflowContext('def_1');
    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$statusCode}'), new EvaluationContext($context)))->toBeNull();
});

it('evaluates component parameters', function () {
    $context = new WorkflowContext('def_1', components: [
        'parameters' => [
            'api-key' => 'secret-123',
        ],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$components.parameters.api-key}'), new EvaluationContext($context)))->toBe('secret-123');
    expect($evaluator->evaluate(new Expression('{$components.parameters.missing}'), new EvaluationContext($context)))->toBeNull();
});

it('evaluates step input references', function () {
    $context = (new WorkflowContext('def_1'))->withStepInputs('create-user', ['name' => 'Alice', 'age' => 30]);
    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.create-user.inputs.name}'), new EvaluationContext($context)))->toBe('Alice');
    expect($evaluator->evaluate(new Expression('{$steps.create-user.inputs.age}'), new EvaluationContext($context)))->toBe(30);
    expect($evaluator->evaluate(new Expression('{$steps.create-user.inputs.missing}'), new EvaluationContext($context)))->toBeNull();
});

it('returns null for step inputs when the step has not run', function () {
    $context = new WorkflowContext('def_1');
    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.ghost.inputs.name}'), new EvaluationContext($context)))->toBeNull();
});

it('evaluates workflow inputs and outputs references', function () {
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowData('login', ['inputs' => ['user' => 'amy'], 'outputs' => ['token' => 'jwt-123']]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$workflows.login.outputs.token}'), new EvaluationContext($context)))->toBe('jwt-123');
    expect($evaluator->evaluate(new Expression('{$workflows.login.inputs.user}'), new EvaluationContext($context)))->toBe('amy');
});

it('returns null for unknown workflow references', function () {
    $context = new WorkflowContext('def_1');
    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$workflows.login.outputs.token}'), new EvaluationContext($context)))->toBeNull();
});

it('evaluates source descriptions', function () {
    $context = new WorkflowContext('def_1');
    $evaluator = new ExpressionEvaluator();

    $doc = (new \ReflectionClass(ArazzoDocument::class))->newInstanceWithoutConstructor();
    $prop = new \ReflectionProperty(ArazzoDocument::class, 'sourceDescriptions');
    $prop->setValue($doc, [
        new SourceDescription('api', 'https://api.example.com', SourceType::Openapi),
    ]);

    expect($evaluator->evaluate(new Expression('{$sourceDescriptions.api.url}'), new EvaluationContext($context, null, $doc)))->toBe('https://api.example.com');
    expect($evaluator->evaluate(new Expression('{$sourceDescriptions.api.type}'), new EvaluationContext($context, null, $doc)))->toBe('openapi');
    expect($evaluator->evaluate(new Expression('{$sourceDescriptions.missing.url}'), new EvaluationContext($context, null, $doc)))->toBeNull();
});

it('evaluates request query and path parts', function () {
    $context = (new WorkflowContext('def_1'))->withStepRequest('step1', [
        'method' => 'GET',
        'url' => 'http://x/pets/42?page=2',
        'query' => ['page' => '2'],
        'path' => ['petId' => 42],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$request.query.page}'), new EvaluationContext($context, 'step1')))->toBe('2');
    expect($evaluator->evaluate(new Expression('{$request.path.petId}'), new EvaluationContext($context, 'step1')))->toBe(42);
    expect($evaluator->evaluate(new Expression('{$steps.step1.request.query.page}'), new EvaluationContext($context, 'step1')))->toBe('2');
    expect($evaluator->evaluate(new Expression('{$steps.step1.request.path.missing}'), new EvaluationContext($context, 'step1')))->toBeNull();
});

it('resolves json pointer suffixes on inputs and outputs (1.1)', function () {
    $context = (new WorkflowContext('def_1'))
        ->withStepOutput('fetch', 'user', ['profile' => ['email' => 'a@b.c']]);

    $evaluator = new ExpressionEvaluator();

    // inputs pointer
    $inputsCtx = new WorkflowContext('def_1', ['user' => ['address' => ['city' => 'Berlin']]]);
    expect($evaluator->evaluate(new Expression('{$inputs.user#/address/city}'), new EvaluationContext($inputsCtx, 's1')))->toBe('Berlin');

    // step-outputs pointer
    expect($evaluator->evaluate(new Expression('{$steps.fetch.outputs.user#/profile/email}'), new EvaluationContext($context, 'other')))
        ->toBe('a@b.c');
});

it('resolves $message.header and $message.payload against the current step response (1.1)', function () {
    $context = (new WorkflowContext('def_1'))->withStepResponse('consume', [
        'statusCode' => 200,
        'headers' => ['X-Trace' => 'tr-9'],
        'body' => ['order' => ['id' => 'ord_7']],
    ]);

    $evaluator = new ExpressionEvaluator();
    $ctx = new EvaluationContext($context, 'consume');

    expect($evaluator->evaluate(new Expression('{$message.header.X-Trace}'), $ctx))->toBe('tr-9')
        ->and($evaluator->evaluate(new Expression('{$message.payload#/order/id}'), $ctx))->toBe('ord_7');
});

it('resolves $self to the document self URI (1.1)', function () {
    $doc = (new \ReflectionClass(ArazzoDocument::class))->newInstanceWithoutConstructor();
    $prop = new \ReflectionProperty(ArazzoDocument::class, 'self');
    $prop->setValue($doc, 'https://api.example.com/workflows.arazzo.yaml');

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$self}'), new EvaluationContext(new WorkflowContext('d'), null, $doc)))
        ->toBe('https://api.example.com/workflows.arazzo.yaml');
});
