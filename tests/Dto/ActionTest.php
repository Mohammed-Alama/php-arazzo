<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureEndAction;
use Alama\LaravelArazzo\Dto\Action\FailureGotoAction;
use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\Enum\ActionKind;

it('builds success actions', function (): void {
    $g = new SuccessGotoAction('go', 'step2', null, []);
    $e = new SuccessEndAction('end', []);

    expect($g->kind)->toBe(ActionKind::Goto)
        ->and($g->stepId)->toBe('step2')
        ->and($e->kind)->toBe(ActionKind::End);
});

it('builds failure actions', function (): void {
    $r = new RetryAction('r', 500, 3, 'step1', null, []);
    expect($r->kind)->toBe(ActionKind::Retry)
        ->and($r->retryLimit)->toBe(3);

    $g = new FailureGotoAction('go', null, 'wfB', []);
    $e = new FailureEndAction('end', []);
    expect($g->workflowId)->toBe('wfB')
        ->and($e->kind)->toBe(ActionKind::End);
});
