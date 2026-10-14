<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\WsdlStepRule;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a wsdl step that names its operation on a wsdl source', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', operationName: 'GetPet')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects operationPath on a wsdl step', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', 'op', '#/paths/pets/get', null, operationName: 'GetPet')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.wsdl_step');
});

it('requires a wsdl source when operationName is set', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', operationName: 'GetPet')])],
        sources: [new SourceDescription('http', '/api', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.wsdl_step');
});

it('ignores steps without wsdl target fields', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', 'op')])]);
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects operationId that references a wsdl source', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', 'op')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.wsdl_step')
        ->and($ec->errors()[0]->message)->toContain('operationId');
});

it('rejects a canonical operationPath that references a wsdl source', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('s', null, '{$sourceDescriptions.soap.url}#/paths/op')])],
        sources: [new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl)],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->message)->toContain('operationPath');
});

it('resolves a qualified operationName to the referenced wsdl source among many', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [
            Fx::step('ok', null, null, null, operationName: '$sourceDescriptions.soap.GetPet'),
            Fx::step('bad', null, null, null, operationName: '$sourceDescriptions.http.GetPet'),
        ])],
        sources: [
            new SourceDescription('http', '/api', SourceType::Openapi),
            new SourceDescription('soap', '/service.wsdl', SourceType::Wsdl),
        ],
    );
    $ec = new ErrorCollector();
    (new WsdlStepRule())->check($document, SymbolTable::build($document), $ec);

    // 'bad' names an operationName against an openapi source -> rejected.
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->message)->toContain('http');
});
