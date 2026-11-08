<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationPathSyntaxRule;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Tests\Support\Fx;

it('flags missing hash, unknown source, empty pointer, and non-slash pointer', function (): void {
    $doc = Fx::doc(
        workflows: [
            Fx::wf('w', [
                Fx::step('a', null, 'no-hash'),
                Fx::step('b', null, 'ghost#/x'),
                Fx::step('c', null, 'src#'),
                Fx::step('d', null, 'src#nolead'),
                Fx::step('e', null, null, null),
            ]),
        ],
        sources: [new SourceDescription('src', '/u', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBeGreaterThanOrEqual(4);
});

it('accepts a canonical source expression that names a declared source', function (): void {
    $doc = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('a', null, '{$sourceDescriptions.src.url}#/pets')])],
        sources: [new SourceDescription('src', '/u', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('accepts a JSON pointer that contains a closing brace', function (): void {
    $doc = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('a', null, '{$sourceDescriptions.src.url}#/a}b')])],
        sources: [new SourceDescription('src', '/u', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('honours the document spec version for the legacy ${...} spelling', function (): void {
    $document = static fn (SpecVersion $version) => Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('a', null, '${sourceDescriptions.src.url}#/pets')])],
        sources: [new SourceDescription('src', '/u', SourceType::Openapi)],
        specVersion: $version,
    );
    $rule = new StepOperationPathSyntaxRule(new ExpressionEngine());

    $v10 = $document(SpecVersion::V1_0);
    $ec10 = new ErrorCollector();
    $rule->check($v10, SymbolTable::build($v10), $ec10);
    expect($ec10->errors())->toBeEmpty();

    $v11 = $document(SpecVersion::V1_1);
    $ec11 = new ErrorCollector();
    $rule->check($v11, SymbolTable::build($v11), $ec11);
    expect($ec11->errors())->toHaveCount(1)
        ->and($ec11->errors()[0]->code)->toBe('step.operationpath_syntax');
});

it('reports a malformed source expression instead of throwing', function (): void {
    $doc = Fx::doc(
        workflows: [Fx::wf('w', [Fx::step('a', null, '{$sourceDescriptions.src.url#/pets')])],
        sources: [new SourceDescription('src', '/u', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule(new ExpressionEngine()))->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.operationpath_syntax');
});
