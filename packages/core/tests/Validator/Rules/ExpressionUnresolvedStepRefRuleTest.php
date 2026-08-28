<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
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

it('allows backward output references without dependsOn as implicit dependencies', function (): void {
    $s1 = Fx::step('first', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $s2 = Fx::step('second', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.first.outputs.y}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s1, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBeEmpty()->and($ec->warnings())->toBeEmpty();
});

it('flags forward output references when the workflow uses no dependsOn', function (): void {
    $s1 = Fx::step('first', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.second.outputs.y}')),
    ]);
    $s2 = Fx::step('second', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s1, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->warnings())->toBeEmpty();
});

it('warns instead of failing on forward references when the workflow uses dependsOn elsewhere', function (): void {
    $s0 = Fx::step('entry', 'op');
    $s0 = new Step($s0->stepId, null, $s0->operationId, null, null, [], null, [], [], [], [], ['other']);
    $s1 = Fx::step('first', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.second.outputs.y}')),
    ]);
    $s2 = Fx::step('second', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s0, $s1, $s2], dep: ['other'])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBeEmpty()
        ->and($ec->warnings())->toHaveCount(1)
        ->and($ec->warnings()[0]->code)->toBe('expr.forward_step_ref');
});

it('still flags a declared-but-missing output on an implicit backward dependency', function (): void {
    $s1 = Fx::step('first', 'op', outputs: ['y' => new Expression('{$response.body#/id}')]);
    $s2 = Fx::step('second', 'op', params: [
        new Parameter('x', ParameterIn::Query, new Expression('{$steps.first.outputs.ghost}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s1, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
