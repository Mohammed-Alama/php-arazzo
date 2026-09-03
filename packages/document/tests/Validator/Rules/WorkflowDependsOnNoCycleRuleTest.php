<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\WorkflowDependsOnNoCycleRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

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
