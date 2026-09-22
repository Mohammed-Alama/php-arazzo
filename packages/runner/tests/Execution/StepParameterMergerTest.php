<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Runner\Execution\StepParameterMerger;
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

    expect(count($merged->io->parameters))->toBe(2)
        ->and(array_map(fn ($p) => $p->name, $merged->io->parameters))->toBe(['apiVersion', 'q']);
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

    expect(count($merged->io->parameters))->toBe(2);

    $pages = array_values(array_filter($merged->io->parameters, fn ($p) => $p instanceof Parameter && $p->name === 'page'));
    expect($pages)->toHaveCount(1)
        ->and($pages[0]->value)->toBe(99);
});

it('treats same name in different locations as distinct parameters', function (): void {
    $step = Fx::step('s', 'op', params: [wfParam('token', ParameterIn::Header, 'hdr')]);
    $workflow = Fx::wf('w', [$step], parameters: [wfParam('token', ParameterIn::Query, 'qry')]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect(count($merged->io->parameters))->toBe(2);
});

it('preserves reusables and never collides them with concrete parameters', function (): void {
    $reusable = new Reusable(reference: '$components.parameters.page');
    $step = Fx::step('s', 'op', params: [new Parameter(name: 'page', in: ParameterIn::Query, value: 7)]);
    $workflow = Fx::wf('w', [$step], parameters: [$reusable]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect(count($merged->io->parameters))->toBe(2)
        ->and($merged->io->parameters[0])->toBeInstanceOf(Reusable::class);
});

it('keeps every other step property intact after merging', function (): void {
    $expression = new Expression('{$inputs.uid}');
    $step = StepFactory::http(
        stepId: 'enrich',
        description: null,
        flow: new StepFlow(
            strictValidation: true,
            idempotencyKey: true,
            idempotencyHeader: 'X-Key',
        ),
        io: new StepIo(outputs: ['name' => $expression]),
        operationId: 'load-op',
    );
    $workflow = Fx::wf('w', [$step], parameters: [wfParam('apiVersion', ParameterIn::Query, '2')]);

    $merged = StepParameterMerger::merge($step, $workflow);

    expect($merged->io->outputs)->toBe($step->io->outputs)
        ->and($merged->flow->strictValidation)->toBeTrue()
        ->and($merged->flow->idempotencyKey)->toBeTrue()
        ->and($merged->flow->idempotencyHeader)->toBe('X-Key')
        ->and($merged->target->operationId)->toBe('load-op');
});
