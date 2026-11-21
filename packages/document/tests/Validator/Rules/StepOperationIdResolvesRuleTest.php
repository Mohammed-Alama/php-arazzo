<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationIdResolvesRule;

function opIdDoc(?string $operationId, array $sources = [], ?array $resolvedOps = null): ArazzoDocument
{
    $step = StepFactory::http(
        's', null,
        new StepFlow(),
        new StepIo(),
        $operationId,
        $operationId === null ? '/path' : null,
    );

    return new ArazzoDocument(
        arazzo: '1.1.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: $sources,
        workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function symbolsFor(array $resolvedOps = []): SymbolTable
{
    $doc = opIdDoc('op');

    return SymbolTable::build($doc, $resolvedOps);
}

it('skips steps with null operationId', function (): void {
    $doc = opIdDoc(null);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('continues when unqualified operationId has no single non-arazo source', function (): void {
    $doc = opIdDoc('getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
        new SourceDescription('other', 'https://other.test/openapi.json', SourceType::Openapi),
    ]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('continues when unqualified operationId has zero non-arazo sources', function (): void {
    $doc = opIdDoc('getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Arazzo),
    ]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags unknown source qualifier in operationId', function (): void {
    $doc = opIdDoc('$sourceDescriptions.unknown.getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.operationid_resolves')
        ->and($ec->errors()[0]->path)->toBe('/workflows/0/steps/0/operationId');
});

it('skips existence check when no resolved operations are available', function (): void {
    $doc = opIdDoc('$sourceDescriptions.api.getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, SymbolTable::build($doc, []), $ec);
    expect($ec->errors())->toBe([]);
});

it('flags unresolved operationId when resolved operations are available', function (): void {
    $doc = opIdDoc('$sourceDescriptions.api.getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $symbols = SymbolTable::build($doc, ['api' => ['otherOp' => true]]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, $symbols, $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.operationid_resolves');
});

it('accepts resolved operationId', function (): void {
    $doc = opIdDoc('$sourceDescriptions.api.getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $symbols = SymbolTable::build($doc, ['api' => ['getThing' => true]]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, $symbols, $ec);
    expect($ec->errors())->toBe([]);
});

it('strips the $sourceDescriptions. prefix when parsing', function (): void {
    $doc = opIdDoc('$sourceDescriptions.api.getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $symbols = SymbolTable::build($doc, ['api' => ['getThing' => true]]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, $symbols, $ec);
    expect($ec->errors())->toBe([]);
});

it('parses qualified operationId with a dot', function (): void {
    $doc = opIdDoc('api.getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $symbols = SymbolTable::build($doc, ['api' => ['getThing' => true]]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, $symbols, $ec);
    expect($ec->errors())->toBe([]);
});

it('parses unqualified operationId when a single non-arazo source exists', function (): void {
    $doc = opIdDoc('getThing', [
        new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi),
    ]);
    $symbols = SymbolTable::build($doc, ['api' => ['getThing' => true]]);
    $ec = new ErrorCollector();
    (new StepOperationIdResolvesRule())->check($doc, $symbols, $ec);
    expect($ec->errors())->toBe([]);
});
