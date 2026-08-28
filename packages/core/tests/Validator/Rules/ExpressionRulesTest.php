<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ExpressionContextMisuseRule;
use Alama\Arazzo\Validator\Rules\ExpressionJsonPointerSyntaxRule;
use Alama\Arazzo\Validator\Rules\ExpressionSyntaxRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedComponentRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedInputRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedSourceRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedStepRefRule;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedWorkflowRefRule;

function stepE(string $id, array $params = [], array $outs = []): Step
{
    return new Step($id, null, 'op', null, null, $params, null, [], [], [], $outs);
}

function docE(array $params = [], array $outs = [], ?array $inputs = ['type' => 'object', 'properties' => ['userId' => ['type' => 'string']]], array $sources = [], array $deps = []): ArazzoDocument
{
    $steps = [stepE('fetch', $params, $outs)];
    $wf = new Workflow('main', null, null, $inputs, $deps, $steps, [], [], [], []);

    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), $sources, [$wf], new Components([], [], [], []), []);
}

it('flags syntactically bad expression', function (): void {
    $doc = docE(params: [new Parameter('id', ParameterIn::Query, new Expression('{$broken'))]);
    $ec = new ErrorCollector();
    (new ExpressionSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('expr.syntax');
});

it('flags unresolved input ref', function (): void {
    $doc = docE(params: [new Parameter('id', ParameterIn::Query, new Expression('{$inputs.ghost}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedInputRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('expr.unresolved_input_ref');
});

it('flags forward step ref', function (): void {
    $s1 = new Step('first', null, 'op', null, null, [new Parameter('x', ParameterIn::Query, new Expression('{$steps.second.outputs.y}'))], null, [], [], [], []);
    $s2 = new Step('second', null, 'op', null, null, [], null, [], [], [], ['y' => new Expression('{$steps.first.outputs.z}')]);
    $wf = new Workflow('main', null, null, null, [], [$s1, $s2], [], [], [], []);
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$wf], new Components([], [], [], []), []);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedStepRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBeGreaterThanOrEqual(1);
});

it('flags workflow ref not in dependsOn', function (): void {
    $doc = docE(outs: ['t' => new Expression('{$workflows.other.outputs.y}')]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedWorkflowRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved source ref', function (): void {
    $doc = docE(params: [new Parameter('u', ParameterIn::Header, new Expression('{$sourceDescriptions.ghost.url}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedSourceRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved component ref', function (): void {
    $doc = docE(params: [new Parameter('c', ParameterIn::Header, new Expression('{$components.parameters.Ghost}'))]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedComponentRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags $response used in parameters', function (): void {
    $doc = docE(params: [new Parameter('c', ParameterIn::Header, new Expression('{$steps.fetch.response.body}'))]);
    $ec = new ErrorCollector();
    (new ExpressionContextMisuseRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad json pointer segment', function (): void {
    $doc = docE(outs: ['t' => new Expression('{$steps.fetch.response.body#/a~9}')]);
    $ec = new ErrorCollector();
    (new ExpressionJsonPointerSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
