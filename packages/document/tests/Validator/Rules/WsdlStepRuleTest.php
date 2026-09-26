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
