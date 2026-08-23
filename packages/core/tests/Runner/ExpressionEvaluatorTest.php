<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\EvaluationContext;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\SourceType;
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

it('evaluates workflow outputs', function () {
    $context = new WorkflowContext('def_1');
    // We need a way to mock or set workflows in the context. If it's missing, let's see what happens.
    // Assuming context gets a withWorkflowOutput method or similar.
    // For now, let's just create it and see it fail.
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
