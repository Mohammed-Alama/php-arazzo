<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ActionGotoTargetResolvesRule;

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
