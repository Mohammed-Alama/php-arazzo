<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Expression\ExpressionInspector;
use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;
use Alama\Arazzo\Expression\SymbolTable;

it('implements the parse/inspect seam', function (): void {
    expect(new ExpressionInspector())->toBeInstanceOf(ExpressionInterface::class);
});

it('parses a valid expression without a syntax error', function (): void {
    $inspector = new ExpressionInspector();

    expect($inspector->parseExpression('$steps.get.outputs.body'))->toBeNull();
});

it('builds a symbol table from a document', function (): void {
    $inspector = new ExpressionInspector();
    $document = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info(title: 'Test', summary: null, description: null, version: '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components(inputs: [], parameters: [], successActions: [], failureActions: []),
        specificationExtensions: [],
    );

    expect($inspector->buildSymbolTable($document))->toBeInstanceOf(SymbolTable::class);
});
