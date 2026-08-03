<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\ExpressionUnresolvedInputRefRule;

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
