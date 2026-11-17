<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ActionOutputTargetExclusivityRule;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts valid step output reference', function (): void {
    $step = Fx::step('fetch', 'op', outputs: ['result' => new Expression('{$steps.fetch.outputs.result}')]);
    $workflow = Fx::wf('w', [$step], outputs: ['out' => new Expression('{$steps.fetch.outputs.result}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts valid input reference', function (): void {
    $workflow = Fx::wf(
        'w',
        [],
        inputs: ['type' => 'object', 'properties' => ['username' => ['type' => 'string']]],
        outputs: ['out' => new Expression('{$inputs.username}')],
    );
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('rejects malformed expression', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$steps.')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('rejects reference to non-existent step', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$steps.missing.outputs.result}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('rejects reference to non-existent workflow', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$workflows.ghost.outputs.foo}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('rejects reference to non-existent input', function (): void {
    $workflow = Fx::wf('w', [], inputs: [], outputs: ['out' => new Expression('{$inputs.missing}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.output_target_ambiguous');
});

it('accepts null and scalar outputs', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out1' => null, 'out2' => 'literal', 'out3' => 42]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts workflow output reference to itself', function (): void {
    $workflow = Fx::wf('w', [], outputs: ['out' => new Expression('{$workflows.w.outputs.foo}')]);
    $doc = Fx::doc(workflows: [$workflow]);
    $ec = new ErrorCollector();
    (new ActionOutputTargetExclusivityRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});
