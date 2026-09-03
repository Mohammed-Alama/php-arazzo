<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;

function makeEmptyArazzoDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('registers and retrieves a document', function (): void {
    $registry = new InMemoryDefinitionRegistry();
    $document = makeEmptyArazzoDocument();

    $id = $registry->register($document);

    expect($registry->get($id))->toBe($document);
});

it('returns null for an unknown id', function (): void {
    $registry = new InMemoryDefinitionRegistry();

    expect($registry->get('unknown'))->toBeNull();
});
