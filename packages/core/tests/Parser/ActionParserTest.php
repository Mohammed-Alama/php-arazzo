<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Parser;

use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Parser\Exceptions\ParserException;
use Alama\Arazzo\Parser\ParseContext;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Spec\Reusable;

class ActionProbe extends Parser
{
    public function pSA(mixed $n, ParseContext $c)
    {
        return $this->parseSuccessAction($n, $c);
    }

    public function pFA(mixed $n, ParseContext $c)
    {
        return $this->parseFailureAction($n, $c);
    }

    public function pOut(mixed $n, ParseContext $c)
    {
        return $this->parseOutputsMap($n, $c);
    }
}

$c = fn () => new ParseContext('/x');

it('parses success goto action', function () use ($c): void {
    $a = (new ActionProbe())->pSA(['name' => 'go', 'type' => 'goto', 'stepId' => 's2'], $c());
    expect($a)->toBeInstanceOf(SuccessGotoAction::class)
        ->and($a->stepId)->toBe('s2');
});

it('parses success end action', function () use ($c): void {
    expect((new ActionProbe())->pSA(['name' => 'stop', 'type' => 'end'], $c()))
        ->toBeInstanceOf(SuccessEndAction::class);
});

it('parses reusable ref for success', function () use ($c): void {
    expect((new ActionProbe())->pSA(['reference' => '$components.successActions.x'], $c()))
        ->toBeInstanceOf(Reusable::class);
});

it('parses failure goto', function () use ($c): void {
    $a = (new ActionProbe())->pFA(['name' => 'go', 'type' => 'goto', 'workflowId' => 'w'], $c());
    expect($a)->toBeInstanceOf(FailureGotoAction::class)
        ->and($a->workflowId)->toBe('w');
});

it('parses retry action', function () use ($c): void {
    $r = (new ActionProbe())->pFA(['name' => 'r', 'type' => 'retry', 'retryAfter' => 500, 'retryLimit' => 2], $c());
    expect($r)->toBeInstanceOf(RetryAction::class)
        ->and($r->retryAfter)->toBe(500.0);
});

it('rejects invalid success action type', function () use ($c): void {
    (new ActionProbe())->pSA(['name' => 'x', 'type' => 'retry'], $c());
})->throws(ParserException::class, 'Invalid action type');

it('parses outputs map of expressions', function () use ($c): void {
    $o = (new ActionProbe())->pOut(['total' => '{$response.body#/total}'], $c());
    expect($o['total'])->toBeInstanceOf(Expression::class);
});
