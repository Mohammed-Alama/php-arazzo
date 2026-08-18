<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedStepRefRule;

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
