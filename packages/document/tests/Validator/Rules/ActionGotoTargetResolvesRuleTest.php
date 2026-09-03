<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ActionGotoTargetResolvesRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('flags unknown workflowId and skips non-goto/retry actions', function (): void {
    $end = new SuccessEndAction('e', []);
    $goto = new SuccessGotoAction('g', null, 'ghost-wf', []);
    $s = Fx::step('s', 'op', onSuccess: [$end, $goto]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionGotoTargetResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags failure goto and retry with unknown targets', function (): void {
    $f = new FailureGotoAction('g', 'ghost', null, []);
    $r = new RetryAction('r', 1, 1, 'ghost2', null, []);
    $s = Fx::step('s', 'op', onFailure: [$f, $r]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionGotoTargetResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});
