<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\Action\FailureGotoAction;
use Alama\Arazzo\Dto\Action\RetryAction;
use Alama\Arazzo\Dto\Action\SuccessEndAction;
use Alama\Arazzo\Dto\Action\SuccessGotoAction;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\ActionGotoTargetResolvesRule;

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
