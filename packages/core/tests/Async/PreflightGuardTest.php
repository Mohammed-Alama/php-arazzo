<?php

declare(strict_types=1);

use Alama\Arazzo\Async\PreflightGuard;
use Alama\Arazzo\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Spec\WorkflowContext;
use Alama\Arazzo\Validator\PreflightFailureException;
use Alama\Arazzo\Validator\PreflightValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

function guardValidator(): PreflightValidator
{
    $registry = new SourceRegistry(new DefaultSourceResolver([
        'https' => new HttpFetcher(new Client(), new HttpFactory()),
    ]));

    $operations = new OpenApiOperationResolver(
        new OpenApiDocumentLoader($registry),
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );

    return new PreflightValidator($registry, $operations, new DomXpathEvaluator());
}

function guardDocument(array $stepOverrides = []): ArazzoDocument
{
    $step = array_merge([
        'stepId' => 's1',
        'operationPath' => '{$sourceDescriptions.api.url}#/paths/~1pets~1{petId}/get',
        'parameters' => [['name' => 'petId', 'in' => 'path', 'value' => 'abc']],
        'successCriteria' => [['condition' => '${response.statusCode} == 200']],
    ], $stepOverrides);

    $arazzo = [
        'arazzo' => '1.0.1',
        'info' => ['title' => 'guard', 'version' => '1.0.0'],
        'sourceDescriptions' => [
            ['name' => 'api', 'url' => 'https://conformance.invalid/api.json', 'type' => 'openapi'],
        ],
        'workflows' => [
            ['workflowId' => 'wf', 'steps' => [$step]],
        ],
    ];

    return (new Parser())->parse(new RawDocument($arazzo, 'memory://guard.json', Format::Json));
}

/**
 * Preflight audits operation-targeted steps: an unknown source description
 * is a guaranteed validation error.
 */
function guardInvalidStep(): array
{
    return ['operationPath' => '{$sourceDescriptions.nosuch.url}#/paths/~1pets/get'];
}

/** @return array{0: InMemoryDefinitionRegistry, 1: string} */
function guardRegistry(array $stepOverrides = []): array
{
    $registry = new InMemoryDefinitionRegistry();
    $definitionId = $registry->register(guardDocument($stepOverrides));

    return [$registry, $definitionId];
}

it('is a no-op when no preflight validator is configured', function (): void {
    [$registry, $definitionId] = guardRegistry();

    expect(fn () => (new PreflightGuard($registry, null))->guard(new WorkflowContext($definitionId)))
        ->not->toThrow(Throwable::class);
});

it('skips resumed runs: recorded steps mean preflight already passed', function (): void {
    [$registry, $definitionId] = guardRegistry(guardInvalidStep()); // would fail if fresh

    $resumed = (new WorkflowContext($definitionId))->withStepResult('s1', ['statusCode' => 200]);

    expect(fn () => (new PreflightGuard($registry, guardValidator()))->guard($resumed))
        ->not->toThrow(Throwable::class);
});

it('skips silently when the definition is not registered', function (): void {
    expect(fn () => (new PreflightGuard(new InMemoryDefinitionRegistry(), guardValidator()))
        ->guard(new WorkflowContext('never-registered')))->not->toThrow(Throwable::class);
});

it('passes a fresh, resolvable document', function (): void {
    [$registry, $definitionId] = guardRegistry();

    // Remote source yields warnings, not errors: still valid.
    expect(fn () => (new PreflightGuard($registry, guardValidator()))->guard(new WorkflowContext($definitionId)))
        ->not->toThrow(PreflightFailureException::class);
});

it('throws PreflightFailureException on an invalid fresh document', function (): void {
    [$registry, $definitionId] = guardRegistry(guardInvalidStep());

    expect(fn () => (new PreflightGuard($registry, guardValidator()))->guard(new WorkflowContext($definitionId)))
        ->toThrow(PreflightFailureException::class, 'Preflight validation failed');
});
