# Arazzo Workflow Engine for PHP & Laravel

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)
[![Ecosystem Feed](https://img.shields.io/badge/docs-ecosystem%20feed-informational)](docs/ECOSYSTEM_FEED.md)
[![Tia Benchmark](https://img.shields.io/badge/docs-tia%20benchmark-success)](docs/TIA-BENCHMARK.md)

> [!WARNING]
> **Work in Progress:** This project is currently under active development and is not yet ready for production use.

This monorepo hosts the complete ecosystem for executing **Arazzo 1.0.0/1.1.0** specifications natively in PHP.

The [Arazzo Specification](https://github.com/OAI/Arazzo-Specification) is an extension of OpenAPI that describes sequences of API calls (workflows). While OpenAPI describes *what* endpoints exist, Arazzo describes *how* to chain those endpoints together to complete complex, multi-step tasks. This ecosystem provides a powerful execution engine to run those workflows dynamically within your PHP applications.

---

## 📦 Packages

This repository is organized as a monorepo containing the following Composer packages:

| Package | Description | Version |
|---------|-------------|---------|
| **[php-arazzo/arazzo-contracts](packages/contracts)** | Shared contracts, DTOs, port interfaces, and parsed document model (Spec, Workflow, Step, SourceDescription, ExecutionState, WorkflowContext). | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-core](packages/core)** | Framework-agnostic PHP engine. Includes the Arazzo parser, validator, document loader, shared services. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-engine](packages/engine)** | Application core: step state machine, execution pipeline, retry policy, telemetry, lock strategies, state stores. Merged from `engine` + `runtime`. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-runner](packages/runner)** | Workflow execution facade (`RunnerFacade`), execution machinery, events. Includes folded-in `events` package. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-document](packages/document)** | Document parsing, validation and vendor-free model types. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-expression](packages/expression)** | Expression engine: parsing, evaluation, criteria evaluation, JSONPath, JSON Pointer. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-evaluation](packages/evaluation)** | Expression evaluation: criteria evaluation, selector evaluation, string interpolation, payload replacement. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-sources](packages/sources)** | Source resolution, fetching, OpenAPI normalization. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzi-protocol-http](packages/protocol-http)** | HTTP/OpenAPI + AsyncAPI vertical slice: normalizers, operation executors, transport, response validation. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-protocol-rpc](packages/protocol-rpc)** | Protocol-Buffer RPC vertical slice: gRPC, gRPC-Web, Twirp and Connect executors. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-protocol-soap](packages/protocol-soap)** | SOAP vertical slice: SOAP executor, SoapResponseTransfer mapper, XSD response validator. | `^1.0.0-alpha.1` |
| **[php-arazzo/arazzo-cli](packages/cli)** | Console terminal edge: `arazzo run`, `validate`, `list`, `explain`, `render` commands. Thin OSS port using `RunnerFacadeInterface`. | `^1.0.0-alpha.1` |

> **Note:** `alama/laravel-arazzo` has been extracted to its own repository at [`php-arazzo/laravel-arazzo`](https://github.com/PHP-Arazzo/laravel-arazzo). Use that repo for Laravel integration.

---

## 🚀 Features

- **Framework-agnostic core**: Works natively with any PSR-compliant PHP application (PSR-7, PSR-18, PSR-3, PSR-16, PSR-14, PSR-11).
- **Sub-workflow invocation**: Steps can reference and execute other workflows (`workflowId` steps).
- **Expression Resolution**: Dynamically resolves `$inputs`, `$steps`, `$response.body`, `$sourceDescriptions`, and custom variables using powerful JSONPath expressions.
- **Dependency Management**: Automatically resolves and guarantees step execution dependencies (`dependsOn`).
- **Success Criteria Evaluation**: Enforces criteria validation natively before proceeding to the next steps.
- **Sub-workflow outputs**: Child workflow outputs are exposed to parent `$steps.<childStepId>.outputs`.
- **Response schema validation**: Validates HTTP responses against OpenAPI response schemas (default `true`, opt-out per step via `x-strict-validation: false`).
- **Strict mode defaults**: Configurable strict validation default at the engine level.
- **Retry policies**: Configurable retry attempts with exponential backoff calculator.
- **Control flow**: Supports `onSuccess`/`onFailure` actions with `goto`, `retry`, and `end` semantics.
- **Cancellation and suspension**: Supports cancellation scopes and suspension/resumption via correlation.
- **Sub-step budgets**: Shared step budget across nested invocations and queue jobs.

---

## 💻 Installation

Depending on your framework, you can install the core or protocol packages.

### For Plain PHP Projects
```bash
composer require php-arazzo/arazzo-core php-arazzo/arazzo-runner php-arazzo/arazzo-protocol-http
```

The `php-arazzo/arazzo-runner` package exposes `RunnerFacade` as the entry point:
```php
use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Sources\SourceGraph;

// 1. Parse the Arazzo document
$loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
$document = (new Parser())->parse($loader->load('workflow.arazzo.yaml'));

// 2. Wire up source resolution
$sourceGraph = SourceGraph::createRegistry(
    new \GuzzleHttp\Client(),
    new \GuzzleHttp\Psr7\HttpFactory()
);

// 3. Execute
$executor = new RunnerFacade(
    documentLoader: $sourceGraph->document(),
    operationResolver: $sourceGraph->operations(),
    evaluationEngine: new \Alama\Arazzo\Expression\ExpressionEngine(),
    openApiExecutor: new \Alama\Arazzo\Protocol\Http\Execution\DefaultOpenApiExecutor(
        new \GuzzleHttp\Client(),
        new \GuzzleHttp\Psr7\HttpFactory()
    ),
    outputExtractor: new \Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor(
        $sourceGraph->operations(),
        new \Alama\Arazzo\Expression\ExpressionEngine(),
        new \Alama\Arazzo\Evaluation\ArazzoCriteriaEvaluator(
            new \Alama\Arazzo\Expression\ExpressionEngine()
        ),
        new \Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator(
            $sourceGraph->operations()
        )
    ),
    schemaValidator: new \Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator(
        $sourceGraph->operations()
    ),
);

$result = $executor->execute(
    $document->workflows[0],
    $document,
    ['inputs' => []]
);
```

---

## 🛠 Usage Overview

*This is a high-level overview. Please consult the README in each individual package directory for detailed documentation.*

### Laravel Example

`alama/laravel-arazzo` is now maintained in its own repository at [`php-arazzo/laravel-arazzo`](https://github.com/PHP-Arazzo/laravel-arazzo).

### Core Engine Example (Framework-Agnostic)

Using the core engine directly with no framework:

```php
use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Sources\SourceGraph;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// 1. Parse the Arazzo document
$loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
$document = (new Parser())->parse($loader->load('LoginAndRetrievePets.arazzo.yaml'));

$workflow = null;
foreach ($document->workflows as $w) {
    if ($w->workflowId === 'loginUserRetrievePet') {
        $workflow = $w;
        break;
    }
}

// 2. Wire up PSR HTTP + source resolution
$sourceGraph = SourceGraph::createRegistry(
    new Client(),
    new HttpFactory()
);

// 3. Execute via RunnerFacade
$executor = new RunnerFacade(
    documentLoader: $sourceGraph->document(),
    operationResolver: $sourceGraph->operations(),
    evaluationEngine: new \Alama\Arazzo\Expression\ExpressionEngine(),
    openApiExecutor: new \Alama\Arazzo\Protocol\Http\Execution\DefaultOpenApiExecutor(
        new Client(),
        new HttpFactory()
    ),
    outputExtractor: new \Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor(
        $sourceGraph->operations(),
        new \Alama\Arazzo\Expression\ExpressionEngine(),
        new \Alama\Arazzo\Evaluation\ArazzoCriteriaEvaluator(
            new \Alama\Arazzo\Expression\ExpressionEngine()
        ),
        new \Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator(
            $sourceGraph->operations()
        )
    ),
    schemaValidator: new \Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator(
        $sourceGraph->operations()
    ),
);

$result = $executor->execute(
    $workflow,
    $document,
    [
        'username' => 'testuser',
        'password' => 'password123',
    ]
);

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

Baselines follow a strict policy — they may freeze style findings, never correctness ones.
See [`docs/BASELINE-POLICY.md`](docs/BASELINE-POLICY.md); `composer run check-baselines`
enforces it.

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

## 📡 Ecosystem feed — Digest

Daily **human-readable** poll of 54 github sources via `gh` (30 `OAI/*`, 4 `usearazzo/*`, 20 runners/validators/generators) — see `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json`. Captures SOAP/WSDL, `application/xml` payloads, actor-in-loop, `MCP`/`CLI`/`A2A`/`gRPC` proposals early and maps each event to `P0-P2` gaps.

> **Digest:** [`docs/ECOSYSTEM_FEED.md`](docs/ECOSYSTEM_FEED.md) — a bounded, evidence-first digest: **Act** (everything worth a decision, with links and what changed), the tracked **watchlist** status table, collapsed **peer releases**, the **meeting agenda**, and a one-line `suppressed:` count. Any contributor can open it without running a command.

> **Digest:** [`docs/ECOSYSTEM_FEED.md`](docs/ECOSYSTEM_FEED.md) (generated) · **State:** `storage/ecosystem-feed/state/`
* **Poll locally:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) or `php scripts/ecosystem/poll.php --fixtures --dry-run` (offline fixtures including `OAI/Arazzo-Specification#533` SOAP + `#410` actor/loop)
* **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --verbose` → `.scratch/ecosystem-triage/<date>.md` → `/to-tickets`
* **Workflow:** `.github/workflows/ecosystem-feed.yml` (`cron 17 6 * * *`, `workflow_dispatch` with `source` filter), `actions/cache` for ETags

All 54 sources polled via `gh api` (`gh` CLI) with `GITHUB_TOKEN` fallback — see `scripts/ecosystem/GhCli.php`.

## Conformance

`php-arazzo/arazzo-core` runs the **official OAI example corpus** through both its synchronous and queued adapters on every change. The current results live in [`docs/CONFORMANCE.md`](docs/CONFORMANCE.md); regenerate with `php scripts/generate-conformance-matrix.php`.