# cli + laravel onto runner public faces Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:
> executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Collapse cli `RunCommand` and laravel `ExecutionBindings` onto the runner's public surface (
`RunnerFacadeInterface` + a new `RunnerGraphBuilderInterface` / `AsyncExecutionGraph` / `AsyncGraphSeams`) so neither
package imports runner internal concrete types, with zero behaviour change.

**Architecture:** The runner ships three new root-namespace public types — a composition-root builder interface, a seams
value type, and a built-graph value object. An internal `AsyncExecutionGraphAssembler` wires the whole async/queue
graph (worker, resumer, outcome handler, protocol executors, engine, persistence) exactly as laravel wires it today. cli
delegates to `RunnerFacade::execute()`. laravel collapses its execution bindings to bind one lazily-built graph and
alias the nodes it, keeping `WorkflowEngine` config-live.

**Tech Stack:** PHP 8.2+ (arrow fns, promoted ctor props, readonly classes — `RunControlFlow` is already
`final readonly`), Pest 5, PHPStan, Pint, Laravel container, Symfony Console.

**Spec:** `docs/superpowers/specs/2026-09-07-migrate-cli-laravel-public-faces-design.md`

## Global Constraints

- No runner/document/expression internal concrete types may be added to either package's wiring; only public faces,
  value types, SPI ports, and the laravel carve-outs named in the spec.
- Behaviour/config/tests unchanged: the runner suite, cli suite, and laravel suite must stay green **without editing
  existing test files** (see ExecutionBindingsTest / WebhookResumeControllerTest / RunExecuteStepJobTest /
  IdempotencyFeatureTest — these encode the freshness semantics the wiring must preserve).
- Every commit runs the `.githooks/pre-commit` gate (docs regen + pint --test + `composer analyse` + `composer test`).
  `docs/generated` drift is auto-staged by the hook — that is expected.
- Never stage or commit `CONTEXT-MAP.md` (unrelated uncommitted edit).
- Test runner commands: `composer run test-runner`, `composer run test-cli`, `composer run test-laravel`; full gate
  `make verify`.
- `OpenApiExecutorInterface` is a runner execution SPI and is exempt from the laravel src seam guard (tests swap it with
  `app()->instance()`).

---

### Task 1: Runner public types (seams, graph value object, builder interface)

**Files:**

- Create: `packages/runner/src/AsyncGraphSeams.php`
- Create: `packages/runner/src/AsyncExecutionGraph.php`
- Create: `packages/runner/src/RunnerGraphBuilderInterface.php`

**Interfaces:**

- Produces: `AsyncGraphSeams` (readonly; ctor accepts the exact fields below with named args), `AsyncExecutionGraph` (
  readonly; accessors `stepExecutor()`, `workflowExecutor()`, `outcomeHandler()`, `resumer()`, `worker()`,
  `expressionResolver()`, `protocolExecutors()`),
  `RunnerGraphBuilderInterface::buildAsync(AsyncGraphSeams): AsyncExecutionGraph`.

- [ ] **Step 1: Write the failing tests**

Create `packages/runner/tests/Execution/AsyncGraphSeamsTest.php`:

```php
<?php

declare(strict_types=1);

it('defaults config knobs for an empty seams bag', function (): void {
    $seams = dummySeams();

    expect($seams->idempotencyEnabled)->toBeFalse()
        ->and($seams->idempotencyHeader)->toBe('Idempotency-Key')
        ->and($seams->strictValidation)->toBeFalse()
        ->and($seams->retryCeiling)->toBe(10)
        ->and($seams->retryBackoffMultiplier)->toBe(1.0)
        ->and($seams->stateTtlSeconds)->toBe(86400);
});
```

Run: `composer run test-runner -- --filter AsyncGraphSeams`
Expected: FAIL — `dummySeams()` undefined.

Create `packages/runner/tests/RunnerGraphBuilderInterfaceTest.php`:

```php
<?php

declare(strict_types=1);

it('declares the single async build entry point', function (): void {
    expect(interface_exists(Alama\Arazzo\Runner\RunnerGraphBuilderInterface::class))->toBeTrue();
});
```

Run: `composer run test-runner -- --filter RunnerGraphBuilderInterfaceTest`
Expected: FAIL — class not found.

- [ ] **Step 2: Run both to verify they fail (done above)**

- [ ] **Step 3: Write the three public types**

`packages/runner/src/AsyncGraphSeams.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Framework-owned ports and config knobs the async execution graph needs.
 * Nullable expression/open-api/transport ports default to runner-built
 * implementations inside the assembler.
 */
final readonly class AsyncGraphSeams
{
    public function __construct(
        public StateStoreInterface $stateStore,
        public QueueDriverInterface $queueDriver,
        public EventLedgerInterface $eventLedger,
        public ExecutionRegistryInterface $executionRegistry,
        public PendingCorrelationRegistryInterface $pendingCorrelationRegistry,
        public DefinitionRegistryInterface $definitionRegistry,
        public LockManagerInterface $lockManager,
        public HttpClientInterface $httpClient,
        public ?OpenApiExecutorInterface $openApiExecutor = null,
        public ?ExpressionResolverInterface $expressionResolver = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?LoggerInterface $logger = null,
        public bool $idempotencyEnabled = false,
        public string $idempotencyHeader = 'Idempotency-Key',
        public bool $strictValidation = false,
        public int $retryCeiling = 10,
        public float $retryBackoffMultiplier = 1.0,
        public int $stateTtlSeconds = 86400,
    ) {}
}
```

`packages/runner/src/AsyncExecutionGraph.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;

/**
 * The assembled async execution graph. Accessors return the concrete runtime
 * handles the framework packages type against; the @internal sweep (#63)
 * owns the advertised-surface story, these are wiring handles.
 */
final readonly class AsyncExecutionGraph
{
    /**
     * @param  list<StepProtocolExecutorInterface>  $protocolExecutors
     */
    public function __construct(
        private StepExecutor $stepExecutor,
        private WorkflowExecutor $workflowExecutor,
        private StepOutcomeHandler $outcomeHandler,
        private CorrelationResumer $resumer,
        private StepExecutionWorker $worker,
        private ExpressionResolverInterface $expressionResolver,
        private array $protocolExecutors,
    ) {}

    public function stepExecutor(): StepExecutor
    {
        return $this->stepExecutor;
    }

    public function workflowExecutor(): WorkflowExecutor
    {
        return $this->workflowExecutor;
    }

    public function outcomeHandler(): StepOutcomeHandler
    {
        return $this->outcomeHandler;
    }

    public function resumer(): CorrelationResumer
    {
        return $this->resumer;
    }

    public function worker(): StepExecutionWorker
    {
        return $this->worker;
    }

    public function expressionResolver(): ExpressionResolverInterface
    {
        return $this->expressionResolver;
    }

    /** @return list<StepProtocolExecutorInterface> */
    public function protocolExecutors(): array
    {
        return $this->protocolExecutors;
    }
}
```

`packages/runner/src/RunnerGraphBuilderInterface.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

/**
 * Composition root for the runner execution graph. Laravel (and any other
 * framework host) supplies ports/config via AsyncGraphSeams and receives a
 * fully-wired AsyncExecutionGraph; the runner owns how everything is built.
 */
interface RunnerGraphBuilderInterface
{
    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph;
}
```

- [ ] **Step 4: Run the tests**

Run: `composer run test-runner -- --filter "AsyncGraphSeamsTest|RunnerGraphBuilderInterfaceTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/runner/src/AsyncGraphSeams.php packages/runner/src/AsyncExecutionGraph.php packages/runner/src/RunnerGraphBuilderInterface.php packages/runner/tests/Execution/AsyncGraphSeamsTest.php packages/runner/tests/RunnerGraphBuilderInterfaceTest.php
git commit -m "feat(runner): add async graph seams, graph value object and builder face — refs #61"
```

---

### Task 2: Internal async assembler + public builder implementation

**Files:**

- Create: `packages/runner/src/Execution/AsyncExecutionGraphAssembler.php`
- Create: `packages/runner/src/RunnerGraphBuilder.php`

**Interfaces:**

- Consumes: `AsyncGraphSeams`, `AsyncExecutionGraph` (Task 1); `RunnerGraphBuilderInterface`.
- Produces:
  `AsyncExecutionGraphAssembler(#ctor(documents, engine, ?ClientInterface, ?RequestFactoryInterface), #assemble(AsyncGraphSeams): AsyncExecutionGraph)`,
  `RunnerGraphBuilder` implementing `RunnerGraphBuilderInterface` (same ctor shape as `RunnerFacade`).

- [ ] **Step 1: Write the failing wiring test**

Create `packages/runner/tests/Execution/AsyncExecutionGraphAssemblerTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\AsyncExecutionGraphAssembler;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;

function seams(
    ?HttpClientInterface $httpClient = null,
    ?LockManagerInterface $lockManager = null,
    ?QueueDriverInterface $queueDriver = null,
): AsyncGraphSeams {
    $stub = static fn (object $i): object => $i;

    return new AsyncGraphSeams(
        stateStore: $stub(new class() implements \Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface
        {
            public function save(string $executionId, array $state, ?int $ttlSeconds = null): void {}
            public function load(string $executionId): ?array { return null; }
        }),
        queueDriver: $queueDriver ?? $stub(new class() implements QueueDriverInterface
        {
            public function dispatch(object $job, ?int $delaySeconds = null): void {}
        }),
        eventLedger: $stub(new class() implements \Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface
        {
            public function append(string $executionId, string $type, array $data = []): void {}
        }),
        executionRegistry: $stub(new class() implements \Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface
        {
            public function start(string $executionId, string $definitionId, string $workflowId): void {}
            public function complete(string $executionId, \Alama\Arazzo\Contracts\Spec\Enum\ExecutionStatus $status): void {}
        }),
        pendingCorrelationRegistry: $stub(new class() implements \Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface
        {
            public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void {}
            public function findByCorrelationId(string $correlationId): ?\Alama\Arazzo\Contracts\Spec\PendingCorrelation { return null; }
            public function consume(string $correlationId): void {}
            public function existsForExecution(string $executionId): bool { return false; }
        }),
        definitionRegistry: $stub(new class() implements \Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface
        {
            public function get(string $definitionId): ?\Alama\Arazzo\Contracts\Spec\ArazzoDocument { return null; }
        }),
        lockManager: $lockManager ?? $stub(new class() implements LockManagerInterface
        {
            public function acquire(string $key, int $ttlSeconds, callable $callback): mixed { return $callback(); }
            public function tryAcquire(string $key, int $ttlSeconds): bool { return true; }
            public function release(string $key): void {}
        }),
        httpClient: $httpClient ?? $stub(new class() implements HttpClientInterface
        {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request, ?float $timeoutSeconds = null): \Psr\Http\Message\ResponseInterface
            {
                return new \GuzzleHttp\Psr7\Response(200);
            }
        }),
        requestFactory: new \GuzzleHttp\Psr7\HttpFactory(),
        retryCeiling: 3,
        retryBackoffMultiplier: 2.5,
        stateTtlSeconds: 60,
    );
}

function internal(AsyncGraphSeams $s): AsyncExecutionGraphAssembler
{
    return new AsyncExecutionGraphAssembler(
        new \Alama\Arazzo\Document\Document(),
        new ExpressionEngine(),
    );
}

it('wires every node and shares one workflow executor across the graph', function (): void {
    $graph = internal(seams())->assemble(seams());

    expect($graph->worker())->toBeInstanceOf(\Alama\Arazzo\Runner\Execution\StepExecutionWorker::class)
        ->and($graph->resumer())->toBeInstanceOf(\Alama\Arazzo\Runner\Execution\CorrelationResumer::class)
        ->and($graph->outcomeHandler())->toBeInstanceOf(\Alama\Arazzo\Runner\Execution\StepOutcomeHandler::class)
        ->and($graph->workflowExecutor())->toBe($graph->workflowExecutor())
        ->and($graph->expressionResolver())->toBeInstanceOf(\Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface::class)
        ->and($graph->protocolExecutors())->toHaveCount(3);
});

it('passes retry knobs and state ttl into the engine and worker', function (): void {
    $graph = internal(seams())->assemble(seams(retryCeiling: 7, retryBackoffMultiplier: 2.5, stateTtlSeconds: 60));

    $workerRef = new ReflectionProperty($graph->worker(), 'stateTtlSeconds');
    $workerRef->setAccessible(true);
    expect($workerRef->getValue($graph->worker()))->toBe(60);
});

it('orders protocol executors subworkflow, http, async', function (): void {
    $graph = internal(seams())->assemble(seams());

    $classes = array_map(fn (StepProtocolExecutorInterface $e): string => (string) (new ReflectionClass($e))->getShortName(), $graph->protocolExecutors());

    expect($classes)->toBe(['SubWorkflowStepExecutor', 'HttpStepExecutor', 'AsyncApiStepExecutor']);
});

it('uses a provided expression resolver instead of building one', function (): void {
    $resolver = \Mockery::mock(\Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface::class);
    $graph = internal(seams())->assemble(seams(expressionResolver: $resolver));

    expect($graph->expressionResolver())->toBe($resolver);
});
```

Note: the test above uses a vararg-free helper; adapt the `seams()` signature to accept
`?ExpressionResolverInterface $expressionResolver = null` and pass it through when implementing Step 3 (keep the stub
lambda style from existing runner tests: `seams()` returns `AsyncGraphSeams`; the strict named-arg call requires every
public field — assign all port fields, default the rest).

Run: `composer run test-runner -- --filter AsyncExecutionGraphAssemblerTest`
Expected: FAIL — `AsyncExecutionGraphAssembler` not found.

- [ ] **Step 2: Run to verify failure (done above)**

- [ ] **Step 3: Implement the assembler**

`packages/runner/src/Execution/AsyncExecutionGraphAssembler.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Evaluation\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Runner\Protocol\HttpStepExecutor;
use Alama\Arazzo\Runner\Protocol\SubWorkflowStepExecutor;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Assembles the async/queue execution graph. Every node is constructed
 * here — hosts supply ports and config through {@see AsyncGraphSeams}.
 */
final class AsyncExecutionGraphAssembler
{
    public function __construct(
        private readonly DocumentInterface $documents,
        private readonly ExpressionEngineInterface $engine,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    public function assemble(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        $client = $this->httpClient ?? new Client();
        $factory = $this->requestFactory ?? new HttpFactory();

        $openApiExecutor = $seams->openApiExecutor ?? new DefaultOpenApiExecutor($client, $factory, $seams->logger);
        $expressionResolver = $seams->expressionResolver ?? new ExecutionExpressionResolver(
            $this->engine,
            new StepOutputExtractor($this->documents, $this->engine),
            new ResponseSchemaValidator($this->documents),
        );

        $workflowEngine = new WorkflowEngine(
            $expressionResolver,
            maxRetryAttempts: $seams->retryCeiling,
            retryBackoffMultiplier: $seams->retryBackoffMultiplier,
        );

        $injector = new IdempotencyKeyInjector(
            enabledDefault: $seams->idempotencyEnabled,
            headerDefault: $seams->idempotencyHeader,
        );

        $stepExecutor = new StepExecutor(
            $openApiExecutor,
            $expressionResolver,
            $this->documents,
            engine: $this->engine,
            strictValidationDefault: $seams->strictValidation,
            injector: $injector,
        );

        $httpStepExecutor = new HttpStepExecutor(
            $openApiExecutor,
            $expressionResolver,
            $this->documents,
            engine: $this->engine,
            strictValidationDefault: $seams->strictValidation,
            injector: $injector,
        );

        $workflowExecutor = new WorkflowExecutor(
            $stepExecutor,
            workflowEngine: $workflowEngine,
            preflight: $this->documents,
        );

        $subWorkflowExecutor = new SubWorkflowStepExecutor($workflowExecutor, $this->engine);
        $invoker = new SubWorkflowInvoker($seams->definitionRegistry, $workflowExecutor, $this->engine);

        $persistence = new RunPersistence(
            $seams->stateStore,
            $seams->eventLedger,
            $seams->executionRegistry,
        );

        $controlFlow = new RunControlFlow(
            workflowEngine: $workflowEngine,
            queueDriver: $seams->queueDriver,
            preflight: $this->documents,
        );

        $outcomeHandler = new StepOutcomeHandler(
            $persistence,
            $controlFlow,
            $seams->pendingCorrelationRegistry,
            $invoker,
            $this->engine,
            $seams->stateTtlSeconds,
        );

        $asyncFactories = $seams->requestFactory ?? new HttpFactory();
        $asyncExecutor = new AsyncApiStepExecutor(
            $seams->pendingCorrelationRegistry,
            $this->engine,
            $seams->httpClient,
            $asyncFactories,
            $asyncFactories,
            $asyncFactories,
        );

        $resumer = new CorrelationResumer(
            $seams->pendingCorrelationRegistry,
            $seams->stateStore,
            $seams->definitionRegistry,
            $expressionResolver,
            $outcomeHandler,
            $seams->eventLedger,
            $seams->lockManager,
        );

        $protocolExecutors = [$subWorkflowExecutor, $httpStepExecutor, $asyncExecutor];

        $worker = new StepExecutionWorker(
            $persistence,
            $seams->lockManager,
            $seams->definitionRegistry,
            $expressionResolver,
            $protocolExecutors,
            $controlFlow,
            $seams->stateTtlSeconds,
        );

        return new AsyncExecutionGraph(
            stepExecutor: $stepExecutor,
            workflowExecutor: $workflowExecutor,
            outcomeHandler: $outcomeHandler,
            resumer: $resumer,
            worker: $worker,
            expressionResolver: $expressionResolver,
            protocolExecutors: $protocolExecutors,
        );
    }
}
```

- [ ] **Step 4: Implement the public builder**

`packages/runner/src/RunnerGraphBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Evaluation\ExpressionEngineInterface;
use Alama\Arazzo\Runner\Execution\AsyncExecutionGraphAssembler;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class RunnerGraphBuilder implements RunnerGraphBuilderInterface
{
    public function __construct(
        private readonly DocumentInterface $documents,
        private readonly ExpressionEngineInterface $engine,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        $assembler = new AsyncExecutionGraphAssembler(
            $this->documents,
            $this->engine,
            $this->httpClient,
            $this->requestFactory,
        );

        return $assembler->assemble($seams);
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `composer run test-runner`
Expected: PASS. (Fix the `seams()` test helper's `expressionResolver` param wiring so the named-arg call compiles —
every public field must be supplied.)

- [ ] **Step 6: Commit**

```bash
git add packages/runner/src/Execution/AsyncExecutionGraphAssembler.php packages/runner/src/RunnerGraphBuilder.php packages/runner/tests/Execution/AsyncExecutionGraphAssemblerTest.php
git commit -m "feat(runner): assemble the async execution graph behind the builder face — refs #61"
```

---

### Task 3: cli RunCommand delegates to RunnerFacade

**Files:**

- Modify: `packages/cli/src/Console/Command/RunCommand.php` (lines 11-19 imports; lines 87-109 assembly; lines 111-125
  output rendering)
- Test: `packages/cli/tests/Console/RunCommandTest.php` (unchanged — must keep passing)

**Interfaces:**

- Consumes:
  `RunnerFacade::execute(ArazzoDocument, string workflowId, array $inputs): array{workflowId,status,outputs,stepsSpent,workflowCallStack,steps: array<string, array{stepId,success,outputs,error}>}`.
- Produces: `RunCommand` constructs `new RunnerFacade($documents, $engine, $this->httpClient)`; output text identical to
  today.

- [ ] **Step 1: Write failing test (guards the output shape)**

Add `packages/cli/tests/Console/RunCommandTest.php` a new `it(...)` that asserts the facade shape contract used by the
command: the facade must expose `steps` keyed by stepId with `stepId`/`success`/`error` fields and a top-level `status`.
Because the facade already ships this, the real harness is the migration itself — Step 3 replaces assembly and the two
existing tests in the file (below) are the regression net:

- `it('runs a workflow end-to-end through the CLI', ...)` asserts `pingFlow: succeeded` and `✔ ping`.
- `it('fails with a clear message for an unknown workflow id', ...)` asserts exit 1 + `unknown workflow`.

Run: `composer run test-cli`
Expected: PASS today (pre-migration baseline).

- [ ] **Step 2: (baseline confirmed above)**

- [ ] **Step 3: Rewrite RunCommand**

Replace the imports block (keep `DocumentLoader`, `Document`, `SourceRegistry`, `ExpressionEngine`, `GuzzleHttp\Client`,
`HttpFactory`, `ClientInterface`, Symfony classes; drop `Runner\Execution\*` imports and add
`Alama\Arazzo\Runner\RunnerFacade`):

```php
use Alama\Arazzo\Cli\Console\DocumentLoader;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\RunnerFacade;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
```

Replace lines 87-109 (assembly + execute) with:

```php
        $client = $this->httpClient ?? new Client();
        $factory = new HttpFactory();

        $engine = new ExpressionEngine();
        $documents = new Document($client, $factory, $this->registry);

        $runner = new RunnerFacade($documents, $engine, $this->httpClient);

        /** @var array<string, mixed> $inputs */
        $result = $runner->execute($document, (string) $workflow->workflowId, $inputs);
```

Replace lines 111-125 (output) with:

```php
        $output->writeln(sprintf('workflow <info>%s</info>: <comment>%s</comment>', $result['workflowId'], $result['status']));

        foreach ($result['steps'] as $stepResult) {
            $status = $stepResult['success'] ? '<info>✔</info>' : '<error>✘</error>';
            $output->writeln(sprintf('  %s %s', $status, $stepResult['stepId']));
        }

        if ($result['outputs'] !== []) {
            $output->writeln('outputs:');
            foreach ($result['outputs'] as $name => $value) {
                $output->writeln(sprintf('  %s = %s', $name, json_encode($value)));
            }
        }

        return $result['status'] === 'succeeded' ? Command::SUCCESS : Command::FAILURE;
```

- [ ] **Step 4: Run the tests + analyse**

Run: `composer run test-cli`
Expected: PASS (both tests, same assertions).

Run: `composer run analyse`
Expected: PASS (phpstan sees the facade shape via the interface's array docblock; if a union/array-shape nit appears,
the interface docblock already casts `steps` — keep the shapes aligned).

- [ ] **Step 5: Commit**

```bash
git add packages/cli/src/Console/Command/RunCommand.php
git commit -m "refactor(cli): run workflows through RunnerFacade instead of assembling internals — refs #61"
```

---

### Task 4: laravel composition — AsyncGraphResolver + ExecutionBindings + FacadeBindings

**Files:**

- Create: `packages/laravel/src/Support/AsyncGraphResolver.php`
- Modify: `packages/laravel/src/Bindings/ExecutionBindings.php` (full rewrite)
- Modify: `packages/laravel/src/Bindings/FacadeBindings.php` (add builder binding)
- Test: `packages/laravel/tests/Bindings/ExecutionBindingsTest.php` (unchanged)

**Interfaces:**

- Consumes: `RunnerGraphBuilderInterface`, `AsyncExecutionGraph`, `AsyncGraphSeams` (Tasks 1-2).
- Produces: container bindings — `AsyncExecutionGraph` (singleton), `StepExecutor`, `WorkflowExecutor`,
  `StepOutcomeHandler`, `CorrelationResumer`, `StepExecutionWorker` (singletons aliasing graph nodes),
  `WorkflowEngine` (config-live singleton).

- [ ] **Step 1: Write failing laravel wiring test**

Create `packages/laravel/tests/Bindings/ExecutionBindingsGraphTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\AsyncExecutionGraph;

it('builds one graph and aliases every node from it', function (): void {
    $graph = app(AsyncExecutionGraph::class);

    expect(app(\Alama\Arazzo\Runner\Execution\StepExecutionWorker::class))->toBe($graph->worker())
        ->and(app(\Alama\Arazzo\Runner\Execution\CorrelationResumer::class))->toBe($graph->resumer())
        ->and(app(\Alama\Arazzo\Runner\Execution\WorkflowExecutor::class))->toBe($graph->workflowExecutor());
});
```

Run: `composer run test-laravel -- --filter ExecutionBindingsGraphTest`
Expected: FAIL — `AsyncExecutionGraph` not bound.

- [ ] **Step 2: Run to verify failure (done above)**

- [ ] **Step 3: Create `Support/AsyncGraphResolver`**

See Global Constraints: it must sample config via `ConfigValue` and ports via `$app->make`/`$app->bound` at resolve
time (fresh per graph build):

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Support;

use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;
use Alama\Arazzo\Runner\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

/** Builds AsyncGraphSeams from the current container state and calls the runner builder. */
final class AsyncGraphResolver
{
    public static function resolve(Container $app): AsyncExecutionGraph
    {
        return $app->make(RunnerGraphBuilderInterface::class)->buildAsync(self::seams($app));
    }

    private static function seams(Container $app): AsyncGraphSeams
    {
        return new AsyncGraphSeams(
            stateStore: $app->make(StateStoreInterface::class),
            queueDriver: $app->make(QueueDriverInterface::class),
            eventLedger: $app->make(EventLedgerInterface::class),
            executionRegistry: $app->make(ExecutionRegistryInterface::class),
            pendingCorrelationRegistry: $app->make(PendingCorrelationRegistryInterface::class),
            definitionRegistry: $app->make(DefinitionRegistryInterface::class),
            lockManager: $app->make(LockManagerInterface::class),
            httpClient: $app->make(HttpClientInterface::class),
            openApiExecutor: $app->bound(OpenApiExecutorInterface::class) ? $app->make(OpenApiExecutorInterface::class) : null,
            expressionResolver: $app->bound(ExpressionResolverInterface::class) ? $app->make(ExpressionResolverInterface::class) : null,
            requestFactory: $app->bound(RequestFactoryInterface::class) ? $app->make(RequestFactoryInterface::class) : null,
            logger: $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            idempotencyEnabled: ConfigValue::bool(config('arazzo.idempotency.enabled', false), false),
            idempotencyHeader: ConfigValue::string(config('arazzo.idempotency.header', 'Idempotency-Key'), 'Idempotency-Key'),
            strictValidation: ConfigValue::bool(config('arazzo.strict_schema_validation', false), false),
            retryCeiling: ConfigValue::int(config('arazzo.retry_ceiling', 10), 10),
            retryBackoffMultiplier: ConfigValue::float(config('arazzo.retry_backoff_multiplier', 1.0), 1.0),
            stateTtlSeconds: ConfigValue::int(config('arazzo.state_ttl', 86400), 86400),
        );
    }
}
```

- [ ] **Step 4: Rewrite `ExecutionBindings` and extend `FacadeBindings`**

`packages/laravel/src/Bindings/ExecutionBindings.php` (full replacement):

```php
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Laravel\Support\AsyncGraphResolver;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Illuminate\Contracts\Container\Container;

/**
 * Execution pipeline: one lazily-sampled AsyncExecutionGraph, with the
 * individual nodes re-exported as singleton aliases. WorkflowEngine keeps
 * a config-live binding so retry config re-reads on forget+resolve.
 */
final class ExecutionBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(AsyncExecutionGraph::class, static fn (Container $app): AsyncExecutionGraph => AsyncGraphResolver::resolve($app));

        $app->singleton(StepExecutor::class, static fn (Container $app): StepExecutor => $app->make(AsyncExecutionGraph::class)->stepExecutor());
        $app->singleton(WorkflowExecutor::class, static fn (Container $app): WorkflowExecutor => $app->make(AsyncExecutionGraph::class)->workflowExecutor());
        $app->singleton(StepOutcomeHandler::class, static fn (Container $app): StepOutcomeHandler => $app->make(AsyncExecutionGraph::class)->outcomeHandler());
        $app->singleton(CorrelationResumer::class, static fn (Container $app): CorrelationResumer => $app->make(AsyncExecutionGraph::class)->resumer());
        $app->singleton(StepExecutionWorker::class, static fn (Container $app): StepExecutionWorker => $app->make(AsyncExecutionGraph::class)->worker());

        // Config-live: retry knobs re-read whenever this node is re-resolved
        // after forgetInstance, matching the historical contract.
        $app->singleton(WorkflowEngine::class, static function (Container $app): WorkflowEngine {
            return new WorkflowEngine(
                $app->make(AsyncExecutionGraph::class)->expressionResolver(),
                maxRetryAttempts: ConfigValue::int(config('arazzo.retry_ceiling', 10), 10),
                retryBackoffMultiplier: ConfigValue::float(config('arazzo.retry_backoff_multiplier', 1.0), 1.0),
            );
        });

        $app->singleton(ExpressionResolverInterface::class, static fn (Container $app): object => $app->make(AsyncExecutionGraph::class)->expressionResolver());
    }
}
```

Note the final binding: `ExpressionResolverInterface` stays resolvable through the graph (other host code and
`RunExecuteStepJobTest`'s `app()->instance(...)` override both work).

`packages/laravel/src/Bindings/FacadeBindings.php` — add the builder binding after the facade binding (imports
`Alama\Arazzo\Runner\RunnerGraphBuilder`, `RunnerGraphBuilderInterface`):

```php
        $app->singleton(RunnerGraphBuilderInterface::class, function (Container $app): RunnerGraphBuilder {
            return new RunnerGraphBuilder(
                $app->make(DocumentInterface::class),
                $app->make(ExpressionEngineInterface::class),
                $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : null,
            );
        });
```

(Add `use Psr\Http\Client\ClientInterface;` to FacadeBindings if not already imported.)

- [ ] **Step 5: Run the laravel suite**

Run: `composer run test-laravel`
Expected: PASS — including the new `ExecutionBindingsGraphTest` and the four existing files that encode freshness
idioms (`ExecutionBindingsTest`, `WebhookResumeControllerTest`, `RunExecuteStepJobTest`, `IdempotencyFeatureTest`) with
**no edits**.

If a test fails, re-check the freshness model from the spec (
`docs/superpowers/specs/2026-09-07-migrate-cli-laravel-public-faces-design.md` «Wiring freshness»): graph builds lazily
at first node resolve, `OpenApiExecutorInterface`/`ExpressionResolverInterface` sampled via `$app->bound()`, config
knobs via `ConfigValue` at that moment.

- [ ] **Step 6: Commit**

```bash
git add packages/laravel/src/Support/AsyncGraphResolver.php packages/laravel/src/Bindings/ExecutionBindings.php packages/laravel/src/Bindings/FacadeBindings.php packages/laravel/tests/Bindings/ExecutionBindingsGraphTest.php
git commit -m "refactor(laravel): consume the runner graph via builder face; drop internal assembly — refs #61"
```

---

### Task 5: LaravelFaceSeamTest — machine-checked guard

**Files:**

- Create: `packages/laravel/tests/Validation/LaravelFaceSeamTest.php`

**Interfaces:**

- Consumes: none.
- Produces: `LARAVEL_FORBIDDEN_SEAM_PREFIXES` const; `laravelSourceFiles()`; `laravelSeamViolations()` returning list of
  `path => prefix`.

- [ ] **Step 1: Write the failing guard test**

```php
<?php

declare(strict_types=1);

/**
 * The laravel package's composition resistors must consume the runner through
 * its public face + value types. Runner execution/protocol internals are
 * forbidden in packages/laravel/src; the OpenApiExecutorInterface SPI is the
 * single exempt runner-execution type (tests swap it via app()->instance()).
 * Document-parser/normalizer carve-outs (Resolver/Persistence/API) are owned
 * by the later @internal sweep (#63).
 */
const LARAVEL_FORBIDDEN_SEAM_PREFIXES = [
    'Alama\\Arazzo\\Runner\\Execution\\',
    'Alama\\Arazzo\\Runner\\Protocol\\',
];

const LARAVEL_SEAM_SPI_ALLOWLIST = [
    'Alama\\Arazzo\\Runner\\Execution\\Interfaces\\OpenApiExecutorInterface',
];

function laravelSourceFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'),
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

/** @return array<string, string> path => violating import */
function laravelSeamViolations(): array
{
    $violations = [];
    foreach (laravelSourceFiles() as $file) {
        $contents = (string) file_get_contents($file);
        foreach (LARAVEL_FORBIDDEN_SEAM_PREFIXES as $prefix) {
            foreach (LARAVEL_SEAM_SPI_ALLOWLIST as $allowed) {
                $pattern = '#^use '.preg_quote($prefix, '#').'#m';
                $restricted = str_replace($allowed, '', $contents);
                if (preg_match_all($pattern, $restricted, $matches)) {
                    $violations[basename($file)] = $prefix.'('.count($matches[0]).' import(s))';
                }
            }
        }
    }

    return $violations;
}

it('keeps laravel wiring off runner execution/protocol internals', function (): void {
    expect(laravelSeamViolations())->toBe([]);
});

it('detects a planted runner-internal import (sanity)', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'seam').'.php';
    file_put_contents($tmp, implode("\n", [
        '<?php',
        'use Alama\Arazzo\Runner\Execution\StepExecutor;',
        'class Probe {}',
    ]));
    $violations = laravelSeamViolationsForFile($tmp);

    expect($violations)->toBe(['StepExecutor' => 'Alama\Arazzo\Runner\Execution\']);

    unlink($tmp);
});

function laravelSeamViolationsForFile(string $file): array
{
    $violations = [];
    $contents = (string) file_get_contents($file);
    foreach (LARAVEL_FORBIDDEN_SEAM_PREFIXES as $prefix) {
        foreach (LARAVEL_SEAM_SPI_ALLOWLIST as $allowed) {
            $restricted = str_replace($allowed, '', $contents);
            if (preg_match_all('#^use '.preg_quote($prefix, '#').'#m', $restricted, $matches)) {
                $violations[basename($file)] = $prefix;
            }
        }
    }

    return $violations;
}
```

Run: `composer run test-laravel -- --filter LaravelFaceSeamTest`
Expected: FAIL on the sanity test — `laravelSeamViolationsForFile` undefined.

- [ ] **Step 2: Run to verify failure (done above)**

- [ ] **Step 3: Deduplicate the scan into one helper**

Refactor so `laravelSeamViolations()` calls `laravelSeamViolationsForFile()` per file (the sanity test then passes; the
src-wide test must pass). Mirror the runner's `RunnerFaceSeamTest` structure. Remember PHP 8.4 + pint: global-namespace
file — reference SPL classes (`RecursiveIteratorIterator`, `RecursiveDirectoryIterator`, `SplFileInfo`) without `use`
statements and without leading backslashes (compare `packages/runner/tests/Validation/RunnerFaceSeamTest.php`).

- [ ] **Step 4: Run + analyse**

Run: `composer run test-laravel -- --filter LaravelFaceSeamTest`
Expected: PASS (both tests).
Run: `composer run test-laravel`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add packages/laravel/tests/Validation/LaravelFaceSeamTest.php
git commit -m "test(laravel): enforce runner public-face seam in laravel wiring — refs #61"
```

---

### Task 6: Full gate + doc drift

**Files:**

- Modify: `docs/generated/*` (auto, via pre-commit hook — expected cosmetic seam drift)
- Documentation: `docs/superpowers/specs/2026-09-07-migrate-cli-laravel-public-faces-design.md` remains source of
  truth (already updated)

- [ ] **Step 1: Run the full gate**

Run: `make verify`
Expected: exit 0 (docs regen → pint → phpstan → pest). The laravel + cli + runner suites pass with no test edits.

- [ ] **Step 2: Draft the PR**

Open PR from the branch with body containing `Closes #61`, listing: runner public types added, cli now delegates to
`RunnerFacade`, laravel consumes `RunnerGraphBuilderInterface` + `AsyncExecutionGraph`, `LaravelFaceSeamTest` guard,
`make verify` green.

- [ ] **Step 3: Final commit (docs drift)**

```bash
git add docs/generated
git commit -m "chore(docs): refresh generated boundary reports — Closes #61"
```

(If pre-commit already swept the drift into earlier commits, stage and commit whatever remains — the PR body, not the
final message, carries `Closes #61`.)
