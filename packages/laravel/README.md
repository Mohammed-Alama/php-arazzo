# alama/laravel-arazzo

Laravel bridge for [`alama/arazzo-core`](../core). Wires the framework-agnostic Arazzo workflow engine into your Laravel application: queue-driven step execution, cache-based locking, Redis-backed hot state, and Eloquent-backed persistence for definitions, executions, events, and pending async correlations.

> [!WARNING]
> **Work in progress.** This package is under active development and not yet ready for production use. APIs, config keys, and migrations may change without notice before a stable `2.0.0` tag.

For how the pieces below fit together internally, see [`docs/architecture/06-laravel-integration.md`](../../docs/architecture/06-laravel-integration.md) in the monorepo root — it documents every service-container binding this package registers.

## Requirements

- PHP `^8.4`, Laravel (via `illuminate/*` contracts your app already provides)
- A Redis connection (used for both the execution lock and hot-state storage — see below)
- A queue driver configured (database, Redis, SQS, etc. — anything Laravel's `Queue` facade supports)
- A database connection for the persisted definitions/executions/events tables

## Installation

```bash
composer require alama/laravel-arazzo
```

The service provider (`Alama\Arazzo\Laravel\LaravelArazzoServiceProvider`) is auto-discovered. Publish the config and run the migrations:

```bash
php artisan vendor:publish --tag="arazzo-config"
php artisan migrate
```

Migrations create: `arazzo_definitions`, `arazzo_executions` (plus a status column added in a follow-up migration), `arazzo_events`, and `arazzo_pending_correlations`.

## Configuration

Published to `config/arazzo.php`:

| Key | Env var | Default | Purpose |
|---|---|---|---|
| `strict_schema_validation` | `ARAZZO_STRICT_SCHEMA_VALIDATION` | `false` | When `true`, every step's HTTP response is validated against its OpenAPI response schema by default (a step can still opt in/out individually via its `x-strict-validation` extension). |
| `idempotency.enabled` | `ARAZZO_IDEMPOTENCY_ENABLED` | `false` | Default for whether an idempotency key header is injected into outgoing requests (per-step override via `x-idempotency-key`). |
| `idempotency.header` | `ARAZZO_IDEMPOTENCY_HEADER` | `Idempotency-Key` | Header name used when idempotency keys are injected. |
| `openai.api_key` | `OPENAI_API_KEY` | `''` | API key for the optional AI-assisted workflow generator (`ArazzoGenerator`), used by the `/generate` builder endpoint. |
| `openai.model` | `OPENAI_MODEL` | `gpt-4o` | Model used for generation. |
| `definitions_table` | — | `arazzo_definitions` | Table backing `DefinitionRegistryInterface`. |
| `executions_table` | — | `arazzo_executions` | Table backing `ExecutionRegistryInterface`. |
| `events_table` | — | `arazzo_events` | Table backing `EventLedgerInterface` (append-only event log per execution). |
| `pending_correlations_table` | — | `arazzo_pending_correlations` | Table backing `PendingCorrelationRegistryInterface` (AsyncAPI suspend/resume). |
| `retry_ceiling` | `ARAZZO_RETRY_CEILING` | `10` | Hard cap on `retryLimit` for any `RetryAction`, regardless of what the workflow document declares. |
| `state_ttl` | `ARAZZO_STATE_TTL` | `86400` | TTL (seconds) for hot `ExecutionState` snapshots in Redis (`RedisHotStateStore`). |
| `webhook_prefix` | `ARAZZO_WEBHOOK_PREFIX` | `api/arazzo` | Route prefix for the builder/webhook API — see Routes below. |

## Usage

There is currently no facade — resolve the core classes from the container (constructor injection or `app()->make()`). A minimal controller/job that runs a workflow synchronously:

```php
use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Execution\WorkflowExecutor;

class RunsCheckoutWorkflow
{
    public function __construct(private WorkflowExecutor $executor)
    {
    }

    public function __invoke(array $inputs): void
    {
        $loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
        $document = (new Parser())->parse($loader->load(resource_path('arazzo/checkout.arazzo.yaml')));

        $result = $this->executor->execute($document->workflows[0], $document, $inputs);

        // $result->status is 'succeeded' or 'failed'
    }
}
```

`WorkflowExecutor` is bound as a singleton with a `WorkflowEngine` injected (see `LaravelArazzoServiceProvider`), so calling `execute()` here already goes through the canonical transition-driven path described in [`docs/architecture/02-execution-lifecycle.md`](../../docs/architecture/02-execution-lifecycle.md) — `onSuccess`/`onFailure` actions, retries, and sub-workflow invocation are all handled.

### Durable, queue-driven execution

For a workflow to actually run across queued jobs (survive a worker restart, suspend on an AsyncAPI step, retry with delay), drive it through `Engine::evaluate()` instead of calling `WorkflowExecutor::execute()` directly — this is what dispatches `ExecuteStepJob`s onto your configured Laravel queue via `LaravelQueueDriver`, coordinated by the per-execution Redis lock (`LaravelRedisLockManager`) and persisted between steps via `RedisHotStateStore`. See [`docs/architecture/02-execution-lifecycle.md`](../../docs/architecture/02-execution-lifecycle.md) (Stage 3B) and [`06-laravel-integration.md`](../../docs/architecture/06-laravel-integration.md) for the full mechanics, including how a definition needs to be registered with `DefinitionRegistryInterface` first so queue workers can look it up by ID.

Run your queue worker as usual:

```bash
php artisan queue:work
```

## Routes

Registered under `config('arazzo.webhook_prefix')` (default `api/arazzo`), on the `api` middleware group:

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/endpoints` | Given a `?spec=` query param (an OpenAPI source URL/path), returns a flat list of `{method, path, operationId, summary, description, tags}` — used by the visual workflow builder. |
| `POST` | `/generate` | Accepts `{openapi, graph}` and returns AI-generated Arazzo YAML via `ArazzoGenerator` (requires `openai.api_key` to be configured). |
| `POST` | `/webhooks/{correlationId}` | External resume endpoint for workflows suspended on an AsyncAPI "receive" step — see `CorrelationResumer`. |

Additionally, `GET /arazzo-builder` (on the `web` middleware group) serves the visual workflow builder UI.

> These routes currently only apply the standard `api`/`web` middleware groups — if you expose this in a non-local environment, add your own auth middleware in front of the webhook endpoint before relying on it.

## What gets bound in the container

Every core interface this package implements — `QueueDriverInterface`, `LockManagerInterface`, `StateStoreInterface`, `DefinitionRegistryInterface`, `ExecutionRegistryInterface`, `EventLedgerInterface`, `PendingCorrelationRegistryInterface`, `HttpClientInterface`, and the full expression/execution pipeline from `alama/arazzo-core` — is bound as a singleton in `LaravelArazzoServiceProvider`. You can override any individual binding in your own service provider (registered after this package's) if you need a different backend (e.g. a non-Redis lock manager).

Full binding-by-binding rationale: [`docs/architecture/06-laravel-integration.md`](../../docs/architecture/06-laravel-integration.md).

## Learn more

- [`docs/architecture/`](../../docs/architecture) — full architecture doc set (start at `01-system-overview.md`)
- [`CHANGELOG.md`](../../CHANGELOG.md) / [`UPGRADING.md`](UPGRADING.md)
- [`alama/arazzo-core`](../core) — the underlying framework-agnostic engine

## License

MIT — see [`LICENSE.md`](../../LICENSE.md).
