<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnExistsRule;

it('flags non-scalar and missing workflow references', function (): void {
    /** @var list<mixed> $dep */
    $dep = [['bad'], 'ghost'];
    $wf = new Workflow('main', null, null, null, $dep, [Fx::step()], [], [], [], []);
    $doc = Fx::doc(workflows: [$wf]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBeGreaterThanOrEqual(2);
});
