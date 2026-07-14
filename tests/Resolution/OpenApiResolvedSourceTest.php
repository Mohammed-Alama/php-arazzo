<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution;

use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;
use Alama\LaravelArazzo\Resolution\OpenApiResolvedSource;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;

function makeOpenApi(string $json): OpenApi
{
    return Reader::readFromJson($json);
}

it('extracts a value via json pointer from openapi (stdClass branch)', function (): void {
    $json = '{"openapi":"3.0.0","info":{"title":"My API","version":"1.0.0"},"paths":{}}';
    $resolved = new OpenApiResolvedSource(makeOpenApi($json));

    expect($resolved->extract('/info/title'))->toBe('My API');
    expect($resolved->extract('/openapi'))->toBe('3.0.0');
});

it('returns the whole document for empty json pointer', function (): void {
    $json = '{"openapi":"3.0.0","info":{"title":"My API","version":"1.0.0"},"paths":{}}';
    $resolved = new OpenApiResolvedSource(makeOpenApi($json));

    $whole = $resolved->extract('');

    expect($whole)->toBeObject();
});

it('throws UnresolvableReferenceException for unknown pointer segment', function (): void {
    $json = '{"openapi":"3.0.0","info":{"title":"My API","version":"1.0.0"},"paths":{}}';
    $resolved = new OpenApiResolvedSource(makeOpenApi($json));

    $resolved->extract('/info/nonexistent');
})->throws(UnresolvableReferenceException::class);

it('extracts from array branch of getSerializableData', function (): void {
    // Build a small openapi doc where a property resolves to an array at some level
    $json = '{"openapi":"3.0.0","info":{"title":"Array Test","version":"1.0.0"},"paths":{},"tags":[{"name":"pets"}]}';
    $resolved = new OpenApiResolvedSource(makeOpenApi($json));

    // tags is a list; index 0 is first tag
    $tagName = $resolved->extract('/tags/0/name');

    expect($tagName)->toBe('pets');
});
