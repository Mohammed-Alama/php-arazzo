<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ExpressionUnresolvedInputRefRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

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
