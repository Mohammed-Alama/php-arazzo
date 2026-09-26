<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;

it('carries only vendor-free collaborators', function (): void {
    $normalized = new NormalizedOpenApiOperation(
        path: '/pets/{id}',
        method: 'get',
        resolvedServerUrl: 'https://example.test',
        pathParameters: ['id' => ['style' => 'simple']],
        queryParameters: [],
        headerParameters: [],
        cookieParameters: [],
        requestBodies: [],
        responses: ['200' => ['contentType' => 'application/json']],
    );

    $operation = new ResolvedOperation(
        source: new SourceDescription(
            name: 'api',
            url: 'https://example.test/openapi.json',
            type: SourceType::Openapi,
        ),
        normalized: $normalized,
    );

    expect($operation->normalized)->toBe($normalized)
        ->and($operation->source->name)->toBe('api');
});

it('does not expose cebe handles on the public surface', function (): void {
    $properties = array_map(
        static fn (\ReflectionProperty $p): string => $p->getName(),
        (new \ReflectionClass(ResolvedOperation::class))->getProperties(),
    );

    expect($properties)->toBe(['source', 'normalized']);
});
