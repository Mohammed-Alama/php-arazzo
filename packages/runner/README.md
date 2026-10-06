# alama/arazzo-runner

The Arazzo workflow execution engine: state, events, policy (retries, timeouts), protocol execution, async queue worker, and telemetry. This is the package a standalone (non-Laravel) consumer uses to run Arazzo workflows end to end.

> [!WARNING]
> **Work in progress.** This package is under active development and not yet ready for production use. APIs and namespaces may change without notice before a stable `2.0.0` tag.

Internally the engine is split into `Sync\` (the transition loop) and `Async\` (the queue-driven worker that re-enters it), guarded by arch tests. For the full map — namespace split, seam ownership, the DIP rule, and the guards that enforce it — see [`docs/architecture/07-runner-package-map.md`](../../docs/architecture/07-runner-package-map.md).

## Requirements

- PHP `^8.4`
- A protocol package to execute steps (e.g. [`alama/arazzo-protocol-http`](../protocol-http)) — the runner itself is protocol-agnostic

## Installation

```bash
composer require alama/arazzo-runner
composer require alama/arazzo-protocol-http
```

## Usage

The entry point is [`RunnerFacade`](src/RunnerFacade.php): hand it your parsed document, an operation resolver, and the protocol value seams, then call `run()`.

```php
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Sources\SourceGraph;
use Alama\Arazzo\Protocol\Http\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Protocol\Http\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Protocol\Http\Execution\StepOutputExtractor;

$documents = SourceGraph::document();
$operationResolver = SourceGraph::operations();
$evaluationEngine = new EvaluationEngine();

// protocol-http value seams (each optional — register only what a step needs)
$executor = new DefaultOpenApiExecutor($httpClient, $requestFactory);
$outputs = new StepOutputExtractor($operationResolver, $evaluationEngine, new ExpressionEngine());
$schema = new ResponseSchemaValidator($operationResolver);

$runner = new RunnerFacade(
    documents: $documents,
    operationResolver: $operationResolver,
    evaluationEngine: $evaluationEngine,
    openApiExecutor: $executor,
    outputExtractor: $outputs,
    schemaValidator: $schema,
);

$result = $runner->run($arazzoDocument, 'workflow-id', ['input' => 'value']);
```

`$result` is the workflow's outputs. Worker-driven (queue-style) execution — `StepExecutionWorker`, `CorrelationResumer`, `AsyncExecutionGraph` — is assembled through `RunnerGraphBuilder`; framework hosts drive that assembly, so standalone consumers start from `RunnerFacade`.

### Laravel

If you use Laravel, install [`alama/laravel-arazzo`](../laravel) instead — its service providers auto-discover the whole graph, and `RunnerFacadeInterface` is bound in the container:

```php
$result = app(RunnerFacadeInterface::class)->run($document, $workflowId, $inputs);
```