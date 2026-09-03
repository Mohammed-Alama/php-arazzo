<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\WorkflowDependsOnExistsRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('flags non-scalar and missing workflow references', function (): void {
    /** @var list<mixed> $dep */
    $dep = [['bad'], 'ghost'];
    $wf = new Workflow('main', null, null, null, $dep, [Fx::step()], [], [], [], []);
    $doc = Fx::doc(workflows: [$wf]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBeGreaterThanOrEqual(2);
});
