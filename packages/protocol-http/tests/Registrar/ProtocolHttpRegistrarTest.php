<?php

declare(strict_types=1);

use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;

it('owns the arazzo.plugins.* tag vocabulary so hosts never name protocol concretes', function (): void {
    $tags = ProtocolHttpRegistrar::tags();

    expect($tags)
        ->toHaveKeys(['arazzo.plugins.*', 'arazzo.plugins.operation-executor'])
        ->and($tags['arazzo.plugins.*'])
        ->toContain(OpenApiSourceNormalizer::class)
        ->toContain(HttpStepExecutor::class)
        ->toContain(AsyncApiStepExecutor::class)
        ->toContain(ResponseSchemaValidator::class)
        ->and($tags['arazzo.plugins.operation-executor'])
        ->toBe([HttpStepExecutor::class, AsyncApiStepExecutor::class]);
});
