<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepNestedWorkflowExistsRule;

it('skips steps without nested workflowId and accepts declared workflows', function (): void {
    $doc = Fx::doc(workflows: [
        Fx::wf('main', [Fx::step('a', null, null, 'main'), Fx::step('b', 'op')]),
    ]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
