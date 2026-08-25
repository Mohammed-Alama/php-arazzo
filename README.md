# Arazzo Workflow Engine for PHP & Laravel

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)
[![Ecosystem Feed](https://img.shields.io/badge/docs-ecosystem%20feed-informational)](docs/ECOSYSTEM_FEED.md)

> [!WARNING]
> **Work in Progress:** This project is currently under active development and is not yet ready for production use.

This monorepo hosts the complete ecosystem for executing **Arazzo 1.0.0/1.1.0** specifications natively in PHP and Laravel. 

The [Arazzo Specification](https://github.com/OAI/Arazzo-Specification) is an extension of OpenAPI that describes sequences of API calls (workflows). While OpenAPI describes *what* endpoints exist, Arazzo describes *how* to chain those endpoints together to complete complex, multi-step tasks. This ecosystem provides a powerful execution engine to run those workflows dynamically within your PHP applications.

---

## 📦 Packages

This repository is organized as a monorepo containing the following packages:

| Package | Description | Version |
|---------|-------------|---------|
| **[alama/arazzo-core](packages/core)** | Framework-agnostic PHP engine. Includes the Arazzo parser, validator, step executor, dependency graph resolution, and JSONPath expression resolver. | `^1.0@alpha` |
| **[alama/laravel-arazzo](packages/laravel)** | Laravel bridge. Deeply integrates the core engine into your Laravel applications with service providers, async queue execution, cache locking, and Eloquent model adapters. | `^2.0@alpha` |

---

## 🚀 Features

- **Full Arazzo Spec Support**: Parses and executes workflows defined in Arazzo 1.0.0 and 1.1.0 formats (YAML/JSON).
- **Expression Resolution**: Dynamically resolves `$inputs`, `$steps`, `$response.body`, and custom variables using powerful JSONPath expressions.
- **Dependency Management**: Automatically resolves and guarantees step execution dependencies (`dependsOn`).
- **Success Criteria Evaluation**: Enforces criteria validation natively before proceeding to the next steps.
- **Framework Agnostic**: The core works natively with any PSR-compliant PHP application (PSR-7, PSR-18, PSR-3, PSR-16, PSR-14, PSR-11).
- **Laravel Native**: Zero-friction setup for Laravel developers. Runs workflows natively on Laravel Queues.

---

## 💻 Installation

Depending on your framework, you can install the core or the Laravel integration package.

### For Laravel Projects
```bash
composer require alama/laravel-arazzo
```
The Service Provider is auto-discovered. You can publish the configuration using:
```bash
php artisan vendor:publish --tag="arazzo-config"
```

### For Plain PHP Projects
```bash
composer require alama/arazzo-core
```

---

## 🛠 Usage Overview

*This is a high-level overview. Please consult the README in each individual package directory for detailed documentation.*

### Laravel Example

> There is currently no `Arazzo` facade. Resolve the core classes from the container instead (constructor injection or `app()->make()`):

```php
use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;

class RunsRideBooking
{
    public function __construct(private WorkflowExecutor $executor)
    {
    }

    public function __invoke(): void
    {
        $loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
        $document = (new Parser())->parse($loader->load(resource_path('arazzo/ride-booking.arazzo.yaml')));

        $result = $this->executor->execute($document->workflows[0], $document, [
            'departure_polygon_id' => 123,
            'destination_polygon_id' => 456,
            'customer_id' => 789,
        ]);

        // $result->status is 'succeeded' or 'failed'
    }
}
```

`WorkflowExecutor` is bound as a singleton by `LaravelArazzoServiceProvider`, already wired for the canonical, action-following execution path. See [`packages/laravel/README.md`](packages/laravel) for durable, queue-driven execution and the full container binding list.

### Core Engine Example (Framework-Agnostic)

Using the core executor manually, with no framework at all:

```php
use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
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
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// 1. Parse the Arazzo document.
$loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
$document = (new Parser())->parse($loader->load('LoginAndRetrievePets.arazzo.yaml'));

$workflow = null;
foreach ($document->workflows as $w) {
    if ($w->workflowId === 'loginUserRetrievePet') {
        $workflow = $w;
        break;
    }
}

// 2. Wire up PSR HTTP + source resolution (fetches the OpenAPI docs the workflow references).
$client = new Client();
$httpFactory = new HttpFactory();

$sourceResolver = new SourceRegistry(new DefaultSourceResolver([
    'http' => new HttpFetcher($client, $httpFactory),
    'https' => new HttpFetcher($client, $httpFactory),
    'file' => new LocalFetcher(),
]));

// 3. Wire up the execution pipeline.
$evaluator = new ExpressionEvaluator();
$operationResolver = new OpenApiOperationResolver(
    new OpenApiDocumentLoader($sourceResolver),
    new OpenApiVersionDetector(),
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
    new DefaultOpenApiExecutor($client, $httpFactory),
    $expressionResolver,
    $operationResolver,
    strictValidationDefault: false,
    injector: new IdempotencyKeyInjector(enabledDefault: false, headerDefault: 'Idempotency-Key'),
);

$executor = new WorkflowExecutor($stepExecutor);

// 4. Run it.
$result = $executor->execute($workflow, $document, [
    'username' => 'testuser',
    'password' => 'password123',
]);

echo "Workflow execution finished with status: {$result->status}\n";

foreach ($result->stepResults as $stepId => $stepResult) {
    $status = $stepResult->success ? 'Success' : 'Failed';
    echo " - Step {$stepId}: {$status}\n";
    if (!empty($stepResult->outputs)) {
        print_r($stepResult->outputs);
    }
}
```

For the full picture of what happens under the hood, see [`docs/architecture/`](docs/architecture) and [`packages/core/README.md`](packages/core).

---

## 🧪 Testing

We use [Pest PHP](https://pestphp.com/) for testing and [PHPStan](https://phpstan.org/) for static analysis.

Run all tests across the monorepo:
```bash
composer test
```

Run static analysis:
```bash
composer analyse
```

Format code:
```bash
composer format
```

For more advanced testing features via Make:
```bash
make verify
```

---

## 🤝 Contributing

We welcome contributions! Please see the issue tracker to report bugs, suggest features, or submit Pull Requests. Be sure to run `make verify` before submitting code to ensure it passes all style, static analysis, and testing requirements.

## 📄 License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.

## 📡 Ecosystem feed — Human Dashboard

Daily **human-readable** poll of 54 github sources via `gh` (30 `OAI/*`, 4 `usearazzo/*`, 20 runners/validators/generators) — see `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json`. Captures SOAP/WSDL, `application/xml` payloads, actor-in-loop, `MCP`/`CLI`/`A2A`/`gRPC` proposals early and maps each event to `P0-P2` gaps.

> **Human dashboard:** [`docs/ECOSYSTEM_FEED.md`](docs/ECOSYSTEM_FEED.md) — regrouped by severity (Breaking / Actionable / Watch),levance, with summary stats, legend, and newest-200 table. Any contributor can open it without running a command.

* **Human:** [`docs/ECOSYSTEM_FEED.md`](docs/ECOSYSTEM_FEED.md) (generated `Human Dashboard`) · **Raw:** `storage/ecosystem-feed/feed.json` + [`docs/generated/ecosystem-feed.json`](docs/generated/ecosystem-feed.json) · **Snapshots:** `storage/ecosystem-feed/snapshots/`
* **Poll locally:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) or `php scripts/ecosystem/poll.php --fixtures --dry-run` (offline fixtures including `OAI/Arazzo-Specification#533` SOAP + `#410` actor/loop)
* **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --verbose` → `.scratch/ecosystem-triage/<date>.md` → `/to-tickets`
* **Workflow:** `.github/workflows/ecosystem-feed.yml` (`cron 17 6 * * *`, `workflow_dispatch` with `source` filter), `actions/cache` for ETags, 30-day prune

All 54 sources polled via `gh api` (`gh` CLI) with `GITHUB_TOKEN` fallback — see `scripts/ecosystem/GhCli.php`.

## Conformance

`alama/arazzo-core` runs the **official OAI example corpus** through both its
synchronous and queued adapters on every change. The current results live in
[`docs/CONFORMANCE.md`](docs/CONFORMANCE.md); regenerate with
`php scripts/generate-conformance-matrix.php`.
