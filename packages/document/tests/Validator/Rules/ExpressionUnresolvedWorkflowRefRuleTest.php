<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedWorkflowRefRule;

it('flags unknown workflow, not-in-dependsOn, missing inputs/outputs; accepts declared inputs/outputs', function (): void {
    $otherInputs = ['type' => 'object', 'properties' => ['i' => []]];
    $other = new Workflow('other', null, null, $otherInputs, [], [Fx::step()], [], [], [
        'o' => new Expression('{$inputs.i}'),
    ], []);
    $main = new Workflow('main', null, null, null, ['other'], [Fx::step()], [], [], [
        'ok' => new Expression('{$workflows.other.outputs.o}'),
        'bad' => new Expression('{$workflows.other.outputs.ghost}'),
        'okIn' => new Expression('{$workflows.other.inputs.i}'),
    ], []);
    $doc = Fx::doc(workflows: [$other, $main]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedWorkflowRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags reference to workflow not in dependsOn', function (): void {
    $other = Fx::wf('other', [Fx::step()], [], null, ['o' => new Expression('{$inputs.x}')]);
    $main = new Workflow('main', null, null, null, [], [Fx::step()], [], [], [
        't' => new Expression('{$workflows.other.outputs.o}'),
    ], []);
    $doc = Fx::doc(workflows: [$other, $main]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedWorkflowRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
