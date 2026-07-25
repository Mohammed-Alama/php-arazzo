<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Dto\Info;

it('exposes specVersion derived from the raw arazzo string', function () {
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
        info: new Info('t', null, null, '1.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );

    expect($doc->specVersion)->toBe(SpecVersion::V1_1)
        ->and($doc->self)->toBeNull();
});

it('carries a root $self URI when provided', function () {
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
        info: new Info('t', null, null, '1.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
        self: 'https://example.com/spec.arazzo.yaml',
    );

    expect($doc->self)->toBe('https://example.com/spec.arazzo.yaml');
});
