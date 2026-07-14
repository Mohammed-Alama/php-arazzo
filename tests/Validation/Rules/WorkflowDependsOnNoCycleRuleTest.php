<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnNoCycleRule;

it('reports a single cycle only', function (): void {
    $a = Fx::wf('a', [Fx::step()], ['b']);
    $b = Fx::wf('b', [Fx::step()], ['c']);
    $c = Fx::wf('c', [Fx::step()], ['a']);
    $doc = Fx::doc(workflows: [$a, $b, $c]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('ignores unknown deps without crashing', function (): void {
    $a = Fx::wf('a', [Fx::step()], ['ghost']);
    $doc = Fx::doc(workflows: [$a]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
