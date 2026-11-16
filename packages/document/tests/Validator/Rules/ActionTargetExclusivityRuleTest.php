<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ActionTargetExclusivityRule;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts single-target goto actions', function (): void {
    $goto = new SuccessGotoAction('g', 's1', null, []);
    $s = Fx::step('s', 'op', onSuccess: [$goto]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionTargetExclusivityRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts single-target failure goto actions', function (): void {
    $goto = new FailureGotoAction('g', 's1', null, []);
    $s = Fx::step('s', 'op', onFailure: [$goto]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionTargetExclusivityRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('accepts single-target retry actions', function (): void {
    $retry = new RetryAction('r', 1.0, 3, 's1', null, []);
    $s = Fx::step('s', 'op', onFailure: [$retry]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionTargetExclusivityRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(0);
});

it('rejects dual-target success goto', function (): void {
    $goto = new SuccessGotoAction('g', 's1', 'wf2', []);
    $s = Fx::step('s', 'op', onSuccess: [$goto]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionTargetExclusivityRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.target_ambiguous');
});

it('rejects dual-target failure goto', function (): void {
    $goto = new FailureGotoAction('g', 's1', 'wf2', []);
    $s = Fx::step('s', 'op', onFailure: [$goto]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionTargetExclusivityRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.target_ambiguous');
});

it('rejects dual-target retry', function (): void {
    $retry = new RetryAction('r', 1.0, 3, 's1', 'wf2', []);
    $s = Fx::step('s', 'op', onFailure: [$retry]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionTargetExclusivityRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('action.target_ambiguous');
});

it('rejects both dual targets in same workflow', function (): void {
    $goto = new SuccessGotoAction('g', 's2', 'w', []);
    $retry = new RetryAction('r', 1.0, 3, 's1', 'wf2', []);
    $s1 = Fx::step('s1', 'op', onFailure: [$retry]);
    $s2 = Fx::step('s2', 'op', onSuccess: [$goto]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s1, $s2])]);
    $ec = new ErrorCollector();
    (new ActionTargetExclusivityRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});
