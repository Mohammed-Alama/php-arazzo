<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedInputRefRule;

it('resolves via workflow.inputs or workflow.parameters and flags missing', function (): void {
    $wf = new Workflow(
        'main', null, null,
        ['type' => 'object', 'properties' => ['inputA' => []]],
        [],
        [Fx::step('s', 'op', params: [
            new Parameter('a', ParameterIn::Query, new Expression('{$inputs.inputA}')),
            new Parameter('b', ParameterIn::Query, new Expression('{$inputs.paramB}')),
            new Parameter('c', ParameterIn::Query, new Expression('{$inputs.missing}')),
        ])],
        [], [], [],
        [new Parameter('paramB', ParameterIn::Query, 'v')],
    );
    $doc = Fx::doc(workflows: [$wf]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedInputRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
