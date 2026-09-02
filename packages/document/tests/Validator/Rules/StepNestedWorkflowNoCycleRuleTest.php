<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepNestedWorkflowNoCycleRule;

it('accepts linear nested workflow chains with no cycle', function (): void {
    $a = Fx::wf('a', [Fx::step('s-a', null, null, 'b')]);
    $b = Fx::wf('b', [Fx::step('s-b', null, null, 'c')]);
    $c = Fx::wf('c', [Fx::step('s-c')]);
    $doc = Fx::doc(workflows: [$a, $b, $c]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('reports a circular nested workflow invocation once per participating node', function (): void {
    $a = Fx::wf('a', [Fx::step('s-a', null, null, 'b')]);
    $b = Fx::wf('b', [Fx::step('s-b', null, null, 'a')]);
    $doc = Fx::doc(workflows: [$a, $b]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2)
        ->and($ec->errors()[0]->code)->toBe('step.nested_workflow_no_cycle')
        ->and($ec->errors()[0]->message)->toContain('Circular nested workflow');
});

it('reports a self-referencing nested workflow', function (): void {
    $a = Fx::wf('a', [Fx::step('s-a', null, null, 'a')]);
    $doc = Fx::doc(workflows: [$a]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('ignores dotted external workflow references when detecting cycles', function (): void {
    $a = Fx::wf('a', [Fx::step('s-a', null, null, 'external.other')]);
    $doc = Fx::doc(workflows: [$a]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('ignores workflow references to undeclared workflows without crashing', function (): void {
    $a = Fx::wf('a', [Fx::step('s-a', null, null, 'ghost')]);
    $doc = Fx::doc(workflows: [$a]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
