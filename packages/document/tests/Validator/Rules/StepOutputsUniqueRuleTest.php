<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOutputsUniqueRule;

function stepWithOutputs(array $outputs): Step
{
    return new Step(
        stepId: 'one',
        description: null,
        target: new StepTarget(),
        flow: new StepFlow(),
        io: new StepIo(outputs: $outputs),
    );
}

function docWith(?array $workflowOutputs = null, ?array $stepOutputs = null): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.1.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow(
            workflowId: 'wf',
            summary: null,
            description: null,
            inputs: null,
            dependsOn: [],
            steps: [stepWithOutputs($stepOutputs ?? [])],
            successActions: [],
            failureActions: [],
            outputs: $workflowOutputs ?? [],
            parameters: [],
        )],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('keeps the reserved rule code', function (): void {
    $r = new StepOutputsUniqueRule();
    expect($r->code())->toBe('step.outputs_unique');
});

it('rejects invalid workflow output key', function (): void {
    $doc = docWith(['bad key!' => 'ok']);
    $ec = new ErrorCollector();
    (new StepOutputsUniqueRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('workflow.outputs_key_pattern')
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/outputs/bad key!');
});

it('rejects invalid step output key', function (): void {
    $doc = docWith(null, ['bad key!' => '$response.body']);
    $ec = new ErrorCollector();
    (new StepOutputsUniqueRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.outputs_key_pattern')
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/steps/0/outputs/bad key!');
});

it('accepts valid dotted dashed underscored output keys', function (): void {
    $doc = docWith(
        ['pets.page-size_2' => 'ok', 'user.name' => 'ok'],
        ['pets.page-size_2' => 'ok'],
    );
    $ec = new ErrorCollector();
    (new StepOutputsUniqueRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBe([]);
});

it('rejects each invalid character class on workflow output keys', function (): void {
    $keys = ['bad key', 'bad/key', 'bad:key', 'bad#key'];
    $doc = docWith(array_combine($keys, array_fill(0, \count($keys), 'ok')));
    $ec = new ErrorCollector();
    (new StepOutputsUniqueRule())->check($doc, SymbolTable::build($doc), $ec);

    $codes = array_map(fn ($e) => $e->code, $ec->errors());
    $paths = array_map(fn ($e) => $e->path, $ec->errors());
    expect($codes)->toHaveCount(4)
        ->and(array_unique($codes))->toBe(['workflow.outputs_key_pattern'])
        ->and($paths)->toBe([
            '/workflows/0/outputs/bad key',
            '/workflows/0/outputs/bad/key',
            '/workflows/0/outputs/bad:key',
            '/workflows/0/outputs/bad#key',
        ]);
});
