<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationIdSourceScopedRule;
use Alama\Arazzo\Tests\Support\Fx;

it('handles null skip, qualified unknown source, qualified known source, and unqualified with single openapi', function (): void {
    $doc = Fx::doc(
        workflows: [
            Fx::wf('w', [
                Fx::step('a', null, 'x#/paths/y', null),
                Fx::step('b', 'ghost.op'),
                Fx::step('c', 'src.op'),
                Fx::step('d', 'op'),
            ]),
        ],
        sources: [
            new SourceDescription('src', '/u', SourceType::Openapi),
            new SourceDescription('src2', '/u2', SourceType::Openapi),
        ],
    );
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});

it('accepts the spec runtime-expression qualified form and rejects an unknown source', function (): void {
    $doc = Fx::doc(
        workflows: [
            Fx::wf('w', [
                Fx::step('a', '$sourceDescriptions.src.op'),
                Fx::step('b', '$sourceDescriptions.ghost.op'),
            ]),
        ],
        sources: [
            new SourceDescription('src', '/u', SourceType::Openapi),
            new SourceDescription('src2', '/u2', SourceType::Openapi),
        ],
    );
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->message)->toContain('$sourceDescriptions.ghost.op');
});

it('allows unqualified operationId with one openapi and one arazzo source', function (): void {
    // Only non-arazo sources count toward the qualification gate, so a single
    // openapi source alongside an arazzo source leaves unqualified operationId
    // allowed.
    $doc = Fx::doc(
        workflows: [
            Fx::wf('w', [
                Fx::step('a', 'op'),
            ]),
        ],
        sources: [
            new SourceDescription('api', '/u', SourceType::Openapi),
            new SourceDescription('wf', '/u2', SourceType::Arazzo),
        ],
    );
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBeEmpty();
});

it('rejects unqualified operationId when only arazzo sources exist', function (): void {
    // Zero non-arazo sources is not "exactly one", so unqualified operationId
    // is rejected even though a source description is present.
    $doc = Fx::doc(
        workflows: [
            Fx::wf('w', [
                Fx::step('a', 'op'),
            ]),
        ],
        sources: [
            new SourceDescription('wf', '/u2', SourceType::Arazzo),
        ],
    );
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.operationid_source_scoped');
});
