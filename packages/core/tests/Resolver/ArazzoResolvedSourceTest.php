<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Resolver\ArazzoResolvedSource;
use Alama\Arazzo\Resolver\Exceptions\UnresolvableReferenceException;

function makeArazzoDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info(title: 'My Workflow', summary: null, description: null, version: '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        rawRoot: null,
    );
}

it('extracts a top-level value via json pointer', function (): void {
    $resolved = new ArazzoResolvedSource(makeArazzoDocument());

    expect($resolved->extract('/arazzo'))->toBe('1.0.0');
});

it('extracts a nested value via json pointer', function (): void {
    $resolved = new ArazzoResolvedSource(makeArazzoDocument());

    expect($resolved->extract('/info/title'))->toBe('My Workflow');
});

it('returns the whole document for empty json pointer', function (): void {
    $resolved = new ArazzoResolvedSource(makeArazzoDocument());

    $whole = $resolved->extract('');

    expect($whole)->toBeArray();
    expect($whole)->toHaveKey('arazzo');
});

it('throws UnresolvableReferenceException for unknown pointer', function (): void {
    $resolved = new ArazzoResolvedSource(makeArazzoDocument());

    $resolved->extract('/info/nonexistent');
})->throws(UnresolvableReferenceException::class);
