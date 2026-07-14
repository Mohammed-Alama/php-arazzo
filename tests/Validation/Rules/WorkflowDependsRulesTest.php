<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnExistsRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowDependsOnNoCycleRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowInputsValidSchemaRule;

function step(string $id): Step { return new Step($id, null, 'op', null, null, [], null, [], [], [], []); }

function wfDep(string $id, array $dep = [], ?array $inputs = null): Workflow
{
    return new Workflow($id, null, null, $inputs, $dep, [step('s')], [], [], [], []);
}

function docWf(array $wfs): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], $wfs, new Components([], [], [], []), []);
}

it('flags dependsOn to unknown workflow', function (): void {
    $doc = docWf([wfDep('a', ['ghost'])]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.dependson_exists');
});

it('accepts valid dependsOn workflow', function (): void {
    $doc = docWf([wfDep('a', ['b']), wfDep('b')]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags cyclic dependsOn', function (): void {
    $doc = docWf([wfDep('a', ['b']), wfDep('b', ['a'])]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.dependson_no_cycle');
});

it('accepts acyclic chain', function (): void {
    $doc = docWf([wfDep('a', ['b']), wfDep('b', ['c']), wfDep('c')]);
    $ec = new ErrorCollector();
    (new WorkflowDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags inputs schema not being an object (type check)', function (): void {
    $doc = docWf([wfDep('a', [], ['type' => 'string'])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags inputs properties not being an object', function (): void {
    $doc = docWf([wfDep('a', [], ['type' => 'object', 'properties' => 'string_instead_of_object'])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags inputs that are sequential arrays instead of objects', function (): void {
    $doc = docWf([wfDep('a', [], ['foo', 'bar'])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('accepts inputs as object schema', function (): void {
    $doc = docWf([wfDep('a', [], ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]])]);
    $ec = new ErrorCollector();
    (new WorkflowInputsValidSchemaRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
