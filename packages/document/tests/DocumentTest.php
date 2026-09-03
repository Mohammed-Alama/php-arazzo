<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;

function minimalFixture(): string
{
    return __DIR__.'/fixtures/document/valid.yaml';
}

it('exposes a single entry-point interface', function (): void {
    expect(new Document())->toBeInstanceOf(DocumentInterface::class);
});

it('loads and parses a document from a YAML file', function (): void {
    $document = new Document();

    expect($document->load(minimalFixture()))->toBeInstanceOf(ArazzoDocument::class);
});

it('parses a raw document', function (): void {
    $document = new Document();
    $raw = new RawDocument([
        'arazzo' => '1.0.0',
        'info' => ['title' => 'Inline', 'version' => '1.0'],
        'workflows' => [],
    ], 'inline://minimal.json', Format::Json);

    expect($document->parse($raw))->toBeInstanceOf(ArazzoDocument::class);
});

it('validates a loaded document as valid', function (): void {
    $document = new Document();
    $result = $document->validate($document->load(minimalFixture()));

    expect($result)->toBeInstanceOf(ValidationResult::class)
        ->and($result->isValid())->toBeTrue();
});

it('runs preflight validation without side effects on a minimal document', function (): void {
    $document = new Document();
    $result = $document->preflight($document->load(minimalFixture()));

    expect($result)->toBeInstanceOf(ValidationResult::class);
});
