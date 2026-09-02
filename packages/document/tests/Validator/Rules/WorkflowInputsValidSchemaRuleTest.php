<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\WorkflowInputsValidSchemaRule;

it('flags list inputs, wrong root type, non-object properties; accepts valid and null inputs', function (): void {
    $wLst = new Workflow('a', null, null, [1, 2, 3], [], [Fx::step()], [], [], [], []);
    $wBadType = new Workflow('b', null, null, ['type' => 'string'], [], [Fx::step()], [], [], [], []);
    $wBadProps = new Workflow('c', null, null, ['type' => 'object', 'properties' => 'notObj'], [], [Fx::step()], [], [], [], []);
    $wOk = new Workflow('d', null, null, ['type' => 'object', 'properties' => ['x' => []]], [], [Fx::step()], [], [], [], []);
    $wNull = new Workflow('e', null, null, null, [], [Fx::step()], [], [], [], []);
    $doc = Fx::doc(workflows: [$wLst, $wBadType, $wBadProps, $wOk, $wNull]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(3);
});
