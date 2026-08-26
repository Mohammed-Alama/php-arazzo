<?php

declare(strict_types=1);

use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Events\RunStarted;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Spec\SourceDocument;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use Alama\Arazzo\Validator\PreflightFailureException;
use Alama\Arazzo\Validator\PreflightValidator;
use Alama\Arazzo\Validator\Severity;
use GuzzleHttp\Psr7\HttpFactory;

const PETSHOP_OPENAPI = [
    'openapi' => '3.0.3',
    'info' => ['title' => 'API', 'version' => '1.0.0'],
    'servers' => [['url' => 'https://api.test']],
    'paths' => [
        '/pets/{petId}' => [
            'get' => [
                'operationId' => 'showPetById',
                'parameters' => [
                    ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'ok',
                        'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                    ],
                ],
            ],
        ],
    ],
];

function preflightDocument(array $overrides = []): ArazzoDocument
{
    $arazzo = array_merge([
        'arazzo' => '1.0.1',
        'info' => ['title' => 'P', 'version' => '1.0.0'],
        'sourceDescriptions' => [
            ['name' => 'api', 'url' => 'https://conformance.invalid/api.json', 'type' => 'openapi'],
        ],
        'workflows' => [
            [
                'workflowId' => 'wf',
                'steps' => [
                    [
                        'stepId' => 'fetch',
                        'operationPath' => '{$sourceDescriptions.api.url}#/paths/~1pets~1{petId}/get',
                        'parameters' => [
                            ['name' => 'petId', 'in' => 'path', 'value' => 'abc'],
                        ],
                        'successCriteria' => [['condition' => '${response.statusCode} == 200']],
                    ],
                ],
            ],
        ],
    ], $overrides);

    return (new Parser())->parse(new RawDocument($arazzo, 'memory://preflight.json', Format::Json));
}

function preflightValidator(?SourceRegistry $registry = null): PreflightValidator
{
    $registry ??= new SourceRegistry(new DefaultSourceResolver([]));
    $operations = new OpenApiOperationResolver(
        new OpenApiDocumentLoader($registry),
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );

    return new PreflightValidator($registry, $operations, new DomXpathEvaluator());
}

it('passes a fully resolvable document with zero diagnostics', function (): void {
    $registry = new SourceRegistry(new DefaultSourceResolver([]));
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://conformance.invalid/api.json', PETSHOP_OPENAPI));

    $result = preflightValidator($registry)->validate(preflightDocument());

    expect($result->isValid())->toBeTrue(json_encode($result->errors));
});

it('treats non-local sources as warnings so remote sources stay usable at runtime', function (): void {
    $result = preflightValidator()->validate(preflightDocument());

    expect($result->isValid())->toBeTrue('warnings do not block execution')
        ->and($result->warnings[0]->code)->toBe('preflight.source_not_local')
        ->and($result->warnings[0]->severity)->toBe(Severity::Warning);
});

it('reports unresolvable operation references against local sources', function (): void {
    $registry = new SourceRegistry(new DefaultSourceResolver([]));
    $broken = PETSHOP_OPENAPI;
    unset($broken['paths']['/pets/{petId}']);
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://conformance.invalid/api.json', $broken));

    $result = preflightValidator($registry)->validate(preflightDocument());

    expect($result->isValid())->toBeFalse()
        ->and($result->errors[0]->code)->toBe('preflight.operation_unresolvable')
        ->and($result->errors[0]->path)->toBe('/workflows/wf/steps/fetch/operationPath');
});

it('rejects unsupported OpenAPI versions in local sources', function (): void {
    $registry = new SourceRegistry(new DefaultSourceResolver([]));
    $future = PETSHOP_OPENAPI;
    $future['openapi'] = '4.0.0';
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://conformance.invalid/api.json', $future));

    $result = preflightValidator($registry)->validate(preflightDocument());

    $codes = array_map(fn ($e) => $e->code, $result->errors);
    expect($codes)->toContain('preflight.unsupported_openapi_version');
});

it('guards the synchronous adapter before any side effect or event fires', function (): void {
    $events = new SimpleEventDispatcher();
    $fired = [];
    $events->subscribe(RunStarted::class, function (object $e) use (&$fired): void {
        $fired[] = $e::class;
    });

    // Reference a source description that does not exist at all:
    // a hard preflight ERROR (unlike merely non-local sources).
    $document = preflightDocument([
        'sourceDescriptions' => [
            ['name' => 'other', 'url' => 'https://conformance.invalid/other.json', 'type' => 'openapi'],
        ],
    ]);
    $resolver = new ArazzoExpressionResolver(
        new ExpressionEvaluator(),
        new ArazzoOutputExtractor(
            (new ReflectionClass(OpenApiOperationResolver::class))->newInstanceWithoutConstructor(),
            new ExpressionEvaluator(),
        ),
        new ArazzoCriteriaEvaluator(new ExpressionEvaluator()),
        new ArazzoSchemaValidator(
            (new ReflectionClass(OpenApiOperationResolver::class))->newInstanceWithoutConstructor(),
        ),
    );

    $executor = new WorkflowExecutor(
        new StepExecutor(
            new DefaultOpenApiExecutor(new FakePsr18Client(), new HttpFactory()),
            $resolver,
            (new ReflectionClass(OpenApiOperationResolver::class))->newInstanceWithoutConstructor(),
        ),
        new WorkflowEngine($resolver),
        events: $events,
        preflight: preflightValidator(),
    );

    try {
        $executor->execute($document->workflows[0], $document, []);
        $this->fail('expected PreflightFailureException');
    } catch (PreflightFailureException $e) {
        expect($e->codeId)->toBe('preflight.failed')
            ->and($e->result->errors)->not->toBeEmpty();
    }

    // Nothing ran: not even RunStarted.
    expect($fired)->toBe([]);
});
