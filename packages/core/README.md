# alama/arazzo-core

Framework-agnostic PHP engine for the [Arazzo Specification](https://github.com/OAI/Arazzo-Specification) 1.0.0/1.1.0: parser, validator, workflow executor, expression resolver, and OpenAPI source resolution.

> [!WARNING]
> **Work in progress.** This package is under active development and not yet ready for production use. APIs may change without notice before a stable `1.0.0` tag.

This package has **no framework dependency**. It depends only on PSR interfaces (`psr/log`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/simple-cache`, `psr/event-dispatcher`, `psr/container`) plus `softcreatr/jsonpath`, `cebe/php-openapi`, and `symfony/yaml`. You supply the PSR implementations (a Guzzle/Symfony HTTP client, a PSR-16 cache, etc.); the engine does the rest.

If you're on Laravel, use [`alama/laravel-arazzo`](../laravel) instead — it wires all of this together for you via a service provider. This package is for everyone else, or for anyone who wants to see (or control) exactly how the wiring works.

For a deep dive into how the pieces below fit together, see [`docs/architecture/`](../../docs/architecture) in the monorepo root, starting with [`01-system-overview.md`](../../docs/architecture/01-system-overview.md).

## Installation

```bash
composer require alama/arazzo-core
```

You'll also need a PSR-18 HTTP client and PSR-17 factories in your project — e.g.:

```bash
composer require guzzlehttp/guzzle
```

## What this package does

1. **Parses** an Arazzo YAML/JSON document into a typed, immutable object model (`Alama\Arazzo\Spec\*`).
2. **Validates** a parsed document against the spec's structural and referential rules (`Alama\Arazzo\Validator\*`) — cycle detection, dangling references, unsupported feature combinations, and more.
3. **Resolves** the external OpenAPI (and nested Arazzo) documents a workflow's `sourceDescriptions` point to (`Alama\Arazzo\Resolver\*`).
4. **Executes** a workflow: runs each step's HTTP call, evaluates `{$...}` runtime expressions against accumulated state, checks `successCriteria`, and follows `onSuccess`/`onFailure` actions (`Alama\Arazzo\Runner\*`).

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Validator\PreflightValidator;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// 1. Parse the Arazzo document.
$loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
$parser = new Parser();
$document = $parser->parse($loader->load(__DIR__ . '/workflow.arazzo.yaml'));

// 2. Wire up PSR HTTP + source resolution (fetches the OpenAPI docs the workflow references).
$httpClient = new Client();
$httpFactory = new HttpFactory();

$sourceResolver = new SourceRegistry(new DefaultSourceResolver([
    'http' => new HttpFetcher($httpClient, $httpFactory),
    'https' => new HttpFetcher($httpClient, $httpFactory),
    'file' => new LocalFetcher(),
]));

// 3. Wire up the execution pipeline.
$evaluator = new ExpressionEvaluator();
$documentLoader = new OpenApiDocumentLoader($sourceResolver);
$versionDetector = new OpenApiVersionDetector();
$operationResolver = new OpenApiOperationResolver(
    $documentLoader,
    $versionDetector,
    new OpenApi30Normalizer(),
    new OpenApi31Normalizer(),
);

$expressionResolver = new ArazzoExpressionResolver(
    $evaluator,
    new ArazzoOutputExtractor($operationResolver, $evaluator),
    new ArazzoCriteriaEvaluator($evaluator),
    new ArazzoSchemaValidator($operationResolver),
);

$stepExecutor = new StepExecutor(
    new DefaultOpenApiExecutor($httpClient, $httpFactory),
    $expressionResolver,
    $operationResolver,
    strictValidationDefault: false,
    injector: new IdempotencyKeyInjector(enabledDefault: false, headerDefault: 'Idempotency-Key'),
);

// Optional: execution preflight (resolves sources/operations/versions
// with zero side effects before the run starts).
$preflight = new PreflightValidator(
    $sourceResolver,
    $operationResolver,
    new DomXpathEvaluator(),
);

// The canonical engine makes every control-flow decision; adapters apply
// its transitions. It is required.
$engine = new WorkflowEngine($expressionResolver, maxRetryAttempts: 10);

$executor = new WorkflowExecutor(
    $stepExecutor,
    workflowEngine: $engine,
    preflight: $preflight, // optional but recommended
);

// 4. Run it.
$workflow = $document->workflows[0];
$result = $executor->execute($workflow, $document, inputs: [
    'customer_id' => 789,
]);

echo $result->status . "\n"; // 'succeeded' or 'failed'
foreach ($result->stepResults as $stepId => $stepResult) {
    echo " - {$stepId}: " . ($stepResult->success ? 'ok' : 'failed') . "\n";
}
```

This is the **synchronous, in-process** execution path — everything runs on one call stack with no queue, lock, or persistence layer. It's the right starting point for scripts, tests, and simple integrations.

For **durable, queue-driven, resumable** execution (steps run as background jobs, workflows survive process restarts, AsyncAPI steps can suspend and wait for an external correlation), you need the additional infrastructure described in [`docs/architecture/02-execution-lifecycle.md`](../../docs/architecture/02-execution-lifecycle.md) and [`06-laravel-integration.md`](../../docs/architecture/06-laravel-integration.md) — `alama/laravel-arazzo` provides all of it out of the box.

## Validating a document

```php
use Alama\Arazzo\Validator\RuleSet;
use Alama\Arazzo\Validator\Validator;

$result = (new Validator(RuleSet::default()))->validate($document);

if (!$result->isValid()) {
    foreach ($result->errors as $error) {
        echo $error . "\n";
    }
}
```

## Supported OpenAPI source versions

Steps resolve operations against OpenAPI **3.0.x** and **3.1.x** sources. Swagger 2.0 sources are detected but currently rejected (`UnsupportedSourceVersionException`).

## Learn more

- [`docs/architecture/01-system-overview.md`](../../docs/architecture/01-system-overview.md) — spec-to-PHP mapping, monorepo layout, domain glossary
- [`docs/architecture/02-execution-lifecycle.md`](../../docs/architecture/02-execution-lifecycle.md) — parsing → execution → completion, in detail
- [`docs/architecture/03-dependency-graph.md`](../../docs/architecture/03-dependency-graph.md) — how `dependsOn` becomes execution order
- [`docs/architecture/04-expression-evaluation.md`](../../docs/architecture/04-expression-evaluation.md) — `{$...}` expressions, JSONPath/XPath selectors
- [`docs/architecture/05-source-resolution.md`](../../docs/architecture/05-source-resolution.md) — fetching and resolving external OpenAPI/Arazzo sources
- [`CONTEXT.md`](CONTEXT.md) — short domain glossary
- [`CHANGELOG.md`](../../CHANGELOG.md) / [`UPGRADING.md`](UPGRADING.md)

## License

MIT — see [`LICENSE.md`](../../LICENSE.md).

## Conformance

The official OAI example corpus is vendored under
`tests/Conformance/corpus/oai/` and executed (parse → validate → run against
a deterministic mock transport) as part of the test suite. See
[`docs/CONFORMANCE.md`](../../docs/CONFORMANCE.md) for the generated matrix.
