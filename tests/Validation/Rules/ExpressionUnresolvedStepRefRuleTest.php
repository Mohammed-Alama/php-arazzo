<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedStepRefRule;

it('flags OutputPart referencing missing step output', function (): void {
    $s1 = Fx::step('first', 'op', outputs: ['y' => new Expression('{$inputs.userId}')]);
    $s2 = Fx::step('second', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.first.outputs.ghost}')),
    ]);
    $doc = Fx::doc(workflows: [
        new Workflow('main', null, null,
            ['type' => 'object', 'properties' => ['userId' => []]],
            [], [$s1, $s2], [], [], [], []),
    ]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags reference to unknown stepId', function (): void {
    $s = Fx::step('a', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.ghost.outputs.y}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
