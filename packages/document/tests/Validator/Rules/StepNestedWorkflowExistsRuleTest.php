<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepNestedWorkflowExistsRule;

it('skips steps without nested workflowId and accepts declared workflows', function (): void {
    $doc = Fx::doc(workflows: [
        Fx::wf('main', [Fx::step('a', null, null, 'main'), Fx::step('b', 'op')]),
    ]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
