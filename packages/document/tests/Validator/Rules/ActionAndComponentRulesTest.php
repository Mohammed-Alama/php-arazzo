<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ActionGotoTargetResolvesRule;
use Alama\Arazzo\Validator\Rules\ActionRetryLimitsRule;
use Alama\Arazzo\Validator\Rules\ActionReusableRefResolvesRule;
use Alama\Arazzo\Validator\Rules\ActionTypeValidRule;
use Alama\Arazzo\Validator\Rules\DocUnknownFieldRule;
use Alama\Arazzo\Validator\Rules\ExtensionsXPrefixRule;

function actionDocSteps(array $steps, ?array $rawRoot = null): ArazzoDocument
{
    $w = new Workflow('w', null, null, null, [], $steps, [], [], [], []);

    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), [], $rawRoot);
}

it('accepts valid action types (no-op passes)', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [new SuccessGotoAction('g', 's', null, [])], [], []);
    $doc = actionDocSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionTypeValidRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags goto with unknown stepId', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [new SuccessGotoAction('g', 'ghost', null, [])], [], []);
    $doc = actionDocSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionGotoTargetResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags negative retry limits', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [], [new RetryAction('r', -5, -1, 's', null, [])], []);
    $doc = actionDocSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionRetryLimitsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});

it('flags unresolved reusable ref', function (): void {
    $s = new Step('s', null, 'op', null, null, [], null, [], [new Reusable('$components.successActions.ghost')], [], []);
    $doc = actionDocSteps([$s]);
    $ec = new ErrorCollector();
    (new ActionReusableRefResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('warns on extension without x- prefix (via extensions preprocessing)', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), ['x-good' => 1], ['x-good' => 1, 'y-bad' => 2]);
    $ec = new ErrorCollector();
    (new ExtensionsXPrefixRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->warnings())->toBe([])->and($ec->errors())->toBe([]);
});

it('flags unknown top-level field', function (): void {
    $raw = ['arazzo' => '1.0.0', 'info' => [], 'workflows' => [], 'weird' => true];
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), [], $raw);
    $ec = new ErrorCollector();
    (new DocUnknownFieldRule(strict: false))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->warnings())->toHaveCount(1)->and($ec->errors())->toBe([]);

    $ec2 = new ErrorCollector();
    (new DocUnknownFieldRule(strict: true))->check($doc, SymbolTable::build($doc), $ec2);
    expect($ec2->errors())->toHaveCount(1)->and($ec2->warnings())->toBe([]);
});
