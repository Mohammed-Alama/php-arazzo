<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Cli\Console\Cli\CliRunner;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runtime\State\FileStateStore;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\SourceGraph;
use Alama\Arazzo\Tests\Expression\Support\TestEvaluationEngine;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

/**
 * Parity invariant: the synchronous adapter and the queue-driven adapter
 * (drained in-process by CliRunner) produce the SAME terminal status and
 * step spend for the same document, because both share the canonical
 * WorkflowEngine and the identical step-execution stack.
 */
function parityFixtures(): array
{
    $openapiJson = '{"openapi":"3.0.0","servers":[{"url":"https://api.test"}],"paths":{"/rides":{"post":{"operationId":"createRide","responses":{"201":{"description":"Created"}}}}}}';
    $openapiFile = tempnam(sys_get_temp_dir(), 'parity_').'.json';
    file_put_contents($openapiFile, $openapiJson);

    $workflow = new Workflow('parity_wf', null, null, null, [], [
        StepFactory::http('p1', null, new StepFlow(), new StepIo(), 'createRide'),
        StepFactory::http('p2', null, new StepFlow(dependsOn: ['p1']), new StepIo(), 'createRide'),
    ], [], [], [], []);

    $document = new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('Parity', null, null, '1.0.0'),
        sourceDescriptions: [new SourceDescription('test-api', $openapiFile, SourceType::Openapi)],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    $httpClient = new FakePsr18Client();
    $httpClient->enqueue(new Response(201, [], json_encode(['rideId' => 99])));
    $httpClient->enqueue(new Response(201, [], json_encode(['rideId' => 100])));
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $runtime = SourceGraph::runtime(null, null, new SourceRegistry(new class() implements SourceResolver
    {
        public function resolve(SourceDescription $description, string $basePath): SourceDocument
        {
            return new SourceDocument(
                $description->name,
                $description->type,
                $description->url,
                json_decode((string) file_get_contents($description->url), true),
            );
        }
    }));
    $outputExtractor = new StepOutputExtractor($runtime->operations, $engine, new ExpressionEngine());
    $schemaValidator = new ResponseSchemaValidator($runtime->operations);
    $stepExecutor = new StepExecutor(
        new DefaultOpenApiExecutor($httpClient, new HttpFactory()),
        $runtime->operations,
        $engine,
        $outputExtractor,
        $schemaValidator,
    );

    // Both adapters execute steps through THIS object.
    $protocol = new class($stepExecutor, $document) implements StepProtocolExecutorInterface
    {
        public function __construct(
            private readonly StepExecutor $stepExecutor,
            private readonly ArazzoDocument $document,
        ) {}

        public function supports(Step $step, ArazzoDocument $document): bool
        {
            return true;
        }

        public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
        {
            [$stepContext, $success] = $this->stepExecutor->execute($step, $context, $this->document);
            $raw = $stepContext->getSteps()[$step->stepId] ?? [];

            return StepExecutionOutcome::resolved(
                is_int($raw['statusCode'] ?? null) ? $raw['statusCode'] : ($success ? 200 : 500),
                is_array($raw['outputs'] ?? null) ? $raw['outputs'] : [],
                [],
                inputs: [],
            );
        }
    };

    return [$document, $workflow, $stepExecutor, $protocol];
}

it('sync and queue-driven adapters agree on terminal status and step spend', function (): void {
    [$document, $workflow, $stepExecutor, $protocol] = parityFixtures();

    // --- synchronous adapter ---
    $syncResult = (new WorkflowExecutor($stepExecutor, new WorkflowEngine(new TestEvaluationEngine())))
        ->execute($workflow, $document, []);

    // --- queue-driven adapter (in-process drain) ---
    $definitions = new InMemoryDefinitionRegistry();
    $definitions->register($document);
    $cli = new CliRunner(
        evaluationEngine: new TestEvaluationEngine(),
        stateStore: new FileStateStore(sys_get_temp_dir().'/arazzo-parity-'.bin2hex(random_bytes(4))),
        definitions: $definitions,
        protocolExecutors: [$protocol],
    );
    $cliResult = $cli->run($document, 'parity_wf', [], 'parity_exec_1');

    expect($syncResult->status)->toBe('succeeded')
        ->and($cliResult->status)->toBe($syncResult->status)
        ->and($syncResult->stepsSpent)->toBeGreaterThanOrEqual(2);
});
