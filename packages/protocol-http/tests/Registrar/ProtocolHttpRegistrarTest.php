<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver;

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

it('defaults strict validation on for the http executor it wires', function (): void {
    $registrar = new ProtocolHttpRegistrar(
        loader: new OpenApiDocumentLoader(Mockery::mock(SourceResolver::class)),
        operationResolver: Mockery::mock(OpenApiOperationResolver::class),
        openApiExecutor: Mockery::mock(OpenApiExecutorInterface::class),
        outputExtractor: Mockery::mock(OutputExtractorInterface::class),
        schemaValidator: Mockery::mock(ResponseValidatorInterface::class),
        evaluationEngine: Mockery::mock(EvaluationEngineInterface::class),
        pendingCorrelations: Mockery::mock(PendingCorrelationRegistryInterface::class),
        httpClient: Mockery::mock(HttpClientInterface::class),
    );

    $http = $registrar->executors()[0];

    expect($http)->toBeInstanceOf(HttpStepExecutor::class)
        ->and((new ReflectionProperty(HttpStepExecutor::class, 'strictValidationDefault'))->getValue($http))->toBeTrue();
});
