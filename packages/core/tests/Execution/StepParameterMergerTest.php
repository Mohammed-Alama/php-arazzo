<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Execution\StepParameterMerger;
use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Tests\Support\Fx;

function wfParam(string $name, ParameterIn $in, mixed $value): Parameter
{
    return new Parameter(name: $name, in: $in, value: $value);
}

it('returns the step unchanged when the workflow has no parameters', function (): void {
    $step = Fx::step('s', 'op', params: [wfParam('q', ParameterIn::Query, 'x')]);

    expect(StepParameterMerger::merge($step, null))->toBe($step)
        ->and(StepParameterMerger::merge($step, Fx::wf('w', [$step])))->toBe($step);
});

it('appends workflow parameters that the step does not define', function (): void {
    $step = Fx::step('s', 'op', params: [wfParam('q', ParameterIn::Query, 'step-value')]);
    $workflow = Fx::wf('w', [$step], parameters: [wfParam('apiVersion', ParameterIn::Query, '2')]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect(count($merged->parameters))->toBe(2)
        ->and(array_map(fn ($p) => $p->name, $merged->parameters))->toBe(['apiVersion', 'q']);
});

it('lets a step parameter override a same-named workflow parameter', function (): void {
    $step = Fx::step('s', 'op', params: [
        wfParam('page', ParameterIn::Query, 99),
    ]);
    $workflow = Fx::wf('w', [$step], parameters: [
        wfParam('page', ParameterIn::Query, 1),
        wfParam('apiToken', ParameterIn::Header, 'tok'),
    ]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect(count($merged->parameters))->toBe(2);

    $pages = array_values(array_filter($merged->parameters, fn ($p) => $p instanceof Parameter && $p->name === 'page'));
    expect($pages)->toHaveCount(1)
        ->and($pages[0]->value)->toBe(99);
});

it('treats same name in different locations as distinct parameters', function (): void {
    $step = Fx::step('s', 'op', params: [wfParam('token', ParameterIn::Header, 'hdr')]);
    $workflow = Fx::wf('w', [$step], parameters: [wfParam('token', ParameterIn::Query, 'qry')]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect(count($merged->parameters))->toBe(2);
});

it('preserves reusables and never collides them with concrete parameters', function (): void {
    $reusable = new Reusable(reference: '$components.parameters.page');
    $step = Fx::step('s', 'op', params: [new Parameter(name: 'page', in: ParameterIn::Query, value: 7)]);
    $workflow = Fx::wf('w', [$step], parameters: [$reusable]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect(count($merged->parameters))->toBe(2)
        ->and($merged->parameters[0])->toBeInstanceOf(Reusable::class);
});

it('keeps every other step property intact after merging', function (): void {
    $expression = new Expression('{$inputs.uid}');
    $step = new Step(
        stepId: 'enrich',
        description: null,
        operationId: 'load-op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['name' => $expression],
        dependsOn: [],
        action: null,
        channelPath: null,
        correlationId: null,
        strictValidation: true,
        idempotencyKey: true,
        idempotencyHeader: 'X-Key',
    );
    $workflow = Fx::wf('w', [$step], parameters: [wfParam('apiVersion', ParameterIn::Query, '2')]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect($merged->outputs)->toBe($step->outputs)
        ->and($merged->strictValidation)->toBeTrue()
        ->and($merged->idempotencyKey)->toBeTrue()
        ->and($merged->idempotencyHeader)->toBe('X-Key')
        ->and($merged->operationId)->toBe('load-op');
});
