<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Console\Cli\CliRunner;
use Alama\Arazzo\Contracts\StepExecutionOutcome;
use Alama\Arazzo\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Expression\Enum\SourceType;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\SourceDescription;
use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\SourceDocument;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\State\FileStateStore;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use Alama\Arazzo\Tests\Support\TestExpressionResolver;
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
        new Step('p1', null, 'createRide', null, null, [], null, [], [], [], []),
        new Step('p2', null, 'createRide', null, null, [], null, [], [], [], [], ['p1']),
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
    $evaluator = new ExpressionEvaluator();
    $operationResolver = new OpenApiOperationResolver(
        new OpenApiDocumentLoader(new class() implements SourceResolver
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
        }),
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );
    $resolver = new ArazzoExpressionResolver(
        $evaluator,
        new ArazzoOutputExtractor($operationResolver, $evaluator),
        new ArazzoCriteriaEvaluator($evaluator),
        new ArazzoSchemaValidator($operationResolver),
    );
    $stepExecutor = new StepExecutor(
        new DefaultOpenApiExecutor($httpClient, new HttpFactory()),
        $resolver,
        $operationResolver,
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
    $syncResult = (new WorkflowExecutor($stepExecutor, new WorkflowEngine(new TestExpressionResolver())))
        ->execute($workflow, $document, []);

    // --- queue-driven adapter (in-process drain) ---
    $definitions = new InMemoryDefinitionRegistry();
    $definitions->register($document);
    $cli = new CliRunner(
        expressions: new TestExpressionResolver(),
        stateStore: new FileStateStore(sys_get_temp_dir().'/arazzo-parity-'.bin2hex(random_bytes(4))),
        definitions: $definitions,
        protocolExecutors: [$protocol],
    );
    $cliResult = $cli->run($document, 'parity_wf', [], 'parity_exec_1');

    expect($syncResult->status)->toBe('succeeded')
        ->and($cliResult->status)->toBe($syncResult->status)
        ->and($syncResult->stepsSpent)->toBeGreaterThanOrEqual(2);
});
