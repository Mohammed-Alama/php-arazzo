<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Evaluation;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Evaluation\DependencyAnalyzer;
use Alama\Arazzo\Evaluation\DependencyGraph;
use Alama\Arazzo\Evaluation\ImplicitDependencies;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\RequestBody;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;
use Alama\Arazzo\Tests\Support\Fx;

it('extracts output references from parameters, body, criteria and correlation ids', function (): void {
    $step = Fx::step('checkout', 'op',
        params: [
            new Parameter('cartId', ParameterIn::Query, new Expression('{$steps.load-cart.outputs.cart}')),
        ],
        body: new RequestBody(
            contentType: 'application/json',
            payload: ['total' => new Expression('{$steps.price.outputs.total}'), 'note' => 'static'],
            replacements: [new PayloadReplacement('/currency', new Expression('{$steps.price.outputs.currency}'))],
        ),
        crit: [
            new SuccessCriterion('{$response.body#/status}', 'pending', null),
            new SuccessCriterion(null, '$statusCode == 200 && $steps.load-cart.outputs.ok == true', null),
        ],
    );
    $step = new Step(
        stepId: $step->stepId,
        description: null,
        operationId: $step->operationId,
        operationPath: null,
        workflowId: null,
        parameters: $step->parameters,
        requestBody: $step->requestBody,
        successCriteria: $step->successCriteria,
        onSuccess: [],
        onFailure: [],
        outputs: [],
        dependsOn: [],
        action: null,
        channelPath: null,
        correlationId: new Expression('{$steps.load-cart.outputs.correlationId}'),
    );

    expect(ImplicitDependencies::fromStep($step))->toBe(['load-cart', 'price']);
});

it('excludes self references and deduplicates', function (): void {
    $step = Fx::step('a', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.a.outputs.self}')),
        new Parameter('y', ParameterIn::Query, new Expression('{$steps.b.outputs.one}')),
        new Parameter('z', ParameterIn::Query, new Expression('{$steps.b.outputs.two}')),
    ]);

    expect(ImplicitDependencies::fromStep($step))->toBe(['b']);
});

it('orders steps by implicit output references without dependsOn', function (): void {
    $b = Fx::step('b', 'op', params: [
        new Parameter('id', ParameterIn::Query, new Expression('{$steps.a.outputs.id}')),
    ]);
    $a = Fx::step('a', 'op', outputs: ['id' => new Expression('{$response.body#/id}')]);

    // b declared FIRST on purpose - implicit edge must reorder
    $graph = new DependencyGraph([$b, $a]);

    expect($graph->getTopologicalOrder())->toBe(['a', 'b'])
        ->and($graph->getCycle())->toBeNull()
        ->and($graph->getEffectiveDependencies('b'))->toBe(['a'])
        ->and($graph->getEffectiveDependencies('a'))->toBe([]);
});

it('detects cycles formed purely by implicit references', function (): void {
    $a = Fx::step('a', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.b.outputs.y}')),
    ]);
    $b = Fx::step('b', 'op', params: [
        new Parameter('y', ParameterIn::Query, new Expression('{$steps.a.outputs.x}')),
    ]);

    $graph = new DependencyGraph([$a, $b]);

    expect($graph->getCycle())->not->toBeNull();
});

it('gates runnable steps on implicit dependencies in the analyzer', function (): void {
    $a = Fx::step('a', 'op', outputs: ['id' => new Expression('{$response.body#/id}')]);
    $b = Fx::step('b', 'op', params: [
        new Parameter('id', ParameterIn::Query, new Expression('{$steps.a.outputs.id}')),
    ]);

    $analyzer = new DependencyAnalyzer(new DependencyGraph([$a, $b]));
    $context = new WorkflowContext('def_1');

    // Nothing has run yet: only "a" is runnable.
    $runnable = array_map(fn ($s) => $s->stepId, $analyzer->getRunnableSteps($context));
    expect($runnable)->toBe(['a']);

    // After "a" succeeds, "b" becomes runnable via the implicit edge.
    $context = $context->withStepStatus('a', StepStatus::Succeeded);
    $runnable = array_map(fn ($s) => $s->stepId, $analyzer->getRunnableSteps($context));
    expect($runnable)->toBe(['b']);
});

it('ignores non-output step references like request or response parts', function (): void {
    $step = Fx::step('c', 'op', params: [
        new Parameter('u', ParameterIn::Query, new Expression('{$steps.auth.request.url}')),
        new Parameter('v', ParameterIn::Query, 'plain string'),
    ]);

    expect(ImplicitDependencies::fromStep($step))->toBe([]);
});
