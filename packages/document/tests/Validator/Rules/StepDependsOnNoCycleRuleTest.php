<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepDependsOnNoCycleRule;

it('accepts a cross-document dependsOn without resolving it offline', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['$sourceDescriptions.other.remoteWorkflow.steps.remoteStep']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.1.0', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects a malformed cross-document dependsOn', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['$sourceDescriptions.other.remoteWorkflow']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.1.0', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_unresolved_reference');
});

it('reports an error when a workflow step contains a dependsOn cycle', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('A', null, new StepTarget(), new StepFlow(dependsOn: ['B']), new StepIo()),
        new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['C']), new StepIo()),
        new Step('C', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_no_cycle')
        ->and($ec->errors()[0]->message)->toContain('A -> B -> C -> A')
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/steps/0/dependsOn');
});

it('reports an error when a workflow step contains an unresolved reference', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('A', null, new StepTarget(), new StepFlow(dependsOn: ['missingStep']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_unresolved_reference')
        ->and($ec->errors()[0]->message)->toContain("reference 'missingStep' from step 'A' does not exist")
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/steps/0/dependsOn');
});

it('passes cleanly for valid step dependencies', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('A', null, new StepTarget(), new StepFlow(), new StepIo()),
        new Step('B', null, new StepTarget(), new StepFlow(dependsOn: ['A']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('accepts a cross-workflow dependsOn to a defined step', function (): void {
    $w2 = new Workflow('w2', null, null, null, [], [
        new Step('s2', null, new StepTarget(), new StepFlow(), new StepIo()),
    ], [], [], [], []);

    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['$workflows.w2.steps.s2']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow, $w2], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects a cross-workflow dependsOn to an undefined workflow', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['$workflows.ghost.steps.x']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_unresolved_reference')
        ->and($ec->errors()[0]->message)->toContain('$workflows.ghost.steps.x')
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/steps/0/dependsOn');
});

it('rejects a cross-workflow dependsOn to an undefined step in a defined workflow', function (): void {
    $w2 = new Workflow('w2', null, null, null, [], [
        new Step('s2', null, new StepTarget(), new StepFlow(), new StepIo()),
    ], [], [], [], []);

    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['$workflows.w2.steps.missing']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow, $w2], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_unresolved_reference')
        ->and($ec->errors()[0]->message)->toContain('$workflows.w2.steps.missing')
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/steps/0/dependsOn');
});

it('detects a cycle that spans workflow boundaries', function (): void {
    $w2 = new Workflow('w2', null, null, null, [], [
        new Step('b', null, new StepTarget(), new StepFlow(dependsOn: ['$workflows.w1.steps.a']), new StepIo()),
    ], [], [], [], []);

    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['$workflows.w2.steps.b']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow, $w2], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_no_cycle')
        ->and($ec->errors()[0]->message)->toContain('w1.a -> w2.b -> w1.a')
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/steps/0/dependsOn');
});

it('stops DFS early once a cycle has been found (line 192 branch)', function (): void {
    $cyclic = new Workflow('cyc', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['b']), new StepIo()),
        new Step('b', null, new StepTarget(), new StepFlow(dependsOn: ['a']), new StepIo()),
    ], [], [], [], []);
    $acyclic = new Workflow('acyc', null, null, null, [], [
        new Step('c', null, new StepTarget(), new StepFlow(), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$cyclic, $acyclic], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_no_cycle');
});

it('resolves implicit output dependencies and flags unresolved implicit refs', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('A', null, new StepTarget(), new StepFlow(), new StepIo(outputs: ['x' => new Expression('{$steps.B.outputs.y}')])),
        new Step('B', null, new StepTarget(), new StepFlow(), new StepIo()),
        new Step('C', null, new StepTarget(), new StepFlow(), new StepIo(outputs: ['z' => new Expression('{$steps.missing.outputs.w}')])),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.1.0', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_unresolved_reference');
});

it('returns null for malformed cross-workflow dependsOn reference', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('a', null, new StepTarget(), new StepFlow(dependsOn: ['$workflows.foo']), new StepIo()),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.dependson_unresolved_reference');
});
