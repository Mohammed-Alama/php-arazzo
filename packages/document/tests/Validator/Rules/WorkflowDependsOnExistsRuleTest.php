<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\WorkflowDependsOnExistsRule;
use Alama\Arazzo\Tests\Support\Fx;

it('flags a reference to an undeclared workflow', function (): void {
    $wf = new Workflow('main', null, null, null, ['ghost'], [Fx::step()], [], [], [], []);
    $doc = Fx::doc(workflows: [$wf]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/dependsOn/0');
});

it('accepts a reference to a declared workflow', function (): void {
    $first = new Workflow('first', null, null, null, [], [Fx::step()], [], [], [], []);
    $second = new Workflow('second', null, null, null, ['first'], [Fx::step()], [], [], [], []);
    $doc = Fx::doc(workflows: [$first, $second]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBe([]);
});
