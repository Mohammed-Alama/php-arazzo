<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

it('implements ExpressionEngineInterface', function () {
    $engine = new ExpressionEngine();

    expect($engine)->toBeInstanceOf(ExpressionEngineInterface::class);
});

it('parses valid and invalid expressions', function () {
    $engine = new ExpressionEngine();

    expect($engine->parseExpression('$inputs.userId'))
        ->toBeNull()
        ->and($engine->parseExpression('invalid expr'))
        ->toBeInstanceOf(ExpressionSyntaxException::class);
});

it('projects expression references statically', function () {
    $engine = new ExpressionEngine();

    $ref = $engine->expressionReferences('$inputs.userId');
    expect($ref)->toBeInstanceOf(ExpressionReference::class)
        ->and($ref->kind)->toBe(ReferenceKind::Input)
        ->and($ref->target)->toBe('userId')
        ->and($engine->expressionReferences('bad'))->toBeNull();
});

it('declares evaluate, evaluateSelector, jsonPath and xpath on the public interface', function () {
    $reflection = new ReflectionClass(ExpressionEngineInterface::class);

    expect($reflection->hasMethod('evaluate'))->toBeTrue()
        ->and($reflection->hasMethod('evaluateSelector'))->toBeTrue()
        ->and($reflection->hasMethod('jsonPath'))->toBeTrue()
        ->and($reflection->hasMethod('queryXPath'))->toBeTrue()
        ->and($reflection->hasMethod('supportedXPathVersions'))->toBeTrue();
});

it('evaluates expressions through the public face', function () {
    $engine = new ExpressionEngine();
    $context = new EvaluationContext((new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 201,
        'body' => ['userId' => 'user-123'],
    ]), 'step1');

    expect($engine->evaluate(new Expression('{$steps.step1.response.body#/userId}'), $context))
        ->toBe('user-123');
});

it('evaluates raw jsonpath queries through the public face', function () {
    $engine = new ExpressionEngine();

    expect($engine->jsonPath('$.store.book[1].title', [
        'store' => ['book' => [
            ['title' => 'A'], ['title' => 'B'],
        ]],
    ]))->toBe('B');
});

it('evaluates xpath queries through the public face', function () {
    $engine = new ExpressionEngine();

    expect($engine->supportedXPathVersions())->toBe(['xpath-10'])
        ->and($engine->queryXPath('<root><user><name>Ada</name></user></root>', '/root/user/name', 'xpath-10'))->toBe('Ada');
});

it('evaluates selectors through the public face', function () {
    $engine = new ExpressionEngine();
    $context = (new WorkflowContext('test'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['userId' => 'user-123'],
    ]);

    $selector = new Selector(type: ExpressionType::JsonPath, selector: '$.userId', context: null);

    expect($engine->evaluateSelector($selector, $context, 'step1'))->toBe('user-123');
});
