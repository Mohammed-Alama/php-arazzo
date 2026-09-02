<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepDependsOnNoCycleRule;

it('reports an error when a workflow step contains a dependsOn cycle', function (): void {
    $workflow = new Workflow('w1', null, null, null, [], [
        new Step('A', null, null, null, null, [], null, [], [], [], [], ['B']),
        new Step('B', null, null, null, null, [], null, [], [], [], [], ['C']),
        new Step('C', null, null, null, null, [], null, [], [], [], [], ['A']),
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
        new Step('A', null, null, null, null, [], null, [], [], [], [], ['missingStep']),
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
        new Step('A', null, null, null, null, [], null, [], [], [], [], []),
        new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']),
    ], [], [], [], []);

    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [$workflow], new Components([], [], [], []), []);

    $ec = new ErrorCollector();
    (new StepDependsOnNoCycleRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});
