<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Dto\Enum\ExpressionType;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Resolver\SelectorEvaluator;
use Alama\Arazzo\Resolver\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\ExpressionEvaluator;
use Alama\Arazzo\Runner\WorkflowContext;

it('evaluates JSONPath selector', function () {
    $xpath = new DomXpathEvaluator();
    $expressions = new ExpressionEvaluator();
    $evaluator = new SelectorEvaluator($xpath, $expressions);

    $selector = new Selector(
        type: ExpressionType::JsonPath,
        selector: '$.inputs.userId',
        context: null,
    );

    $context = new WorkflowContext(
        definitionId: 'test',
        inputs: ['userId' => 'user-123'],
    );

    $result = $evaluator->evaluate($selector, $context, 'step1');

    expect($result)->toBe('user-123');
});

it('evaluates JSONPointer selector', function () {
    $xpath = new DomXpathEvaluator();
    $expressions = new ExpressionEvaluator();
    $evaluator = new SelectorEvaluator($xpath, $expressions);

    $selector = new Selector(
        type: ExpressionType::JsonPointer,
        selector: '/inputs/userId',
        context: null,
    );

    $context = new WorkflowContext(
        definitionId: 'test',
        inputs: ['userId' => 'user-123'],
    );

    $result = $evaluator->evaluate($selector, $context, 'step1');

    expect($result)->toBe('user-123');
});

it('evaluates XPath selector', function () {
    $xpath = new DomXpathEvaluator();
    $expressions = new ExpressionEvaluator();
    $evaluator = new SelectorEvaluator($xpath, $expressions);

    $xml = '<root><inputs><userId>user-123</userId></inputs></root>';

    $selector = new Selector(
        type: ExpressionType::XPath,
        selector: '//inputs/userId/text()',
        context: '{$inputs.xmlData}', // Using expression to select the XML data from context
    );

    $context = new WorkflowContext(
        definitionId: 'test',
        inputs: ['xmlData' => $xml],
    );

    $result = $evaluator->evaluate($selector, $context, 'step1');

    expect($result)->toBe('user-123');
});

it('evaluates selector with context expression', function () {
    $xpath = new DomXpathEvaluator();
    $expressions = new ExpressionEvaluator();
    $evaluator = new SelectorEvaluator($xpath, $expressions);

    $selector = new Selector(
        type: ExpressionType::JsonPointer,
        selector: '/userId',
        context: '{$inputs.userObject}',
    );

    $context = new WorkflowContext(
        definitionId: 'test',
        inputs: [
            'userObject' => [
                'userId' => 'user-123',
                'name' => 'John Doe',
            ],
        ],
    );

    $result = $evaluator->evaluate($selector, $context, 'step1');

    expect($result)->toBe('user-123');
});
