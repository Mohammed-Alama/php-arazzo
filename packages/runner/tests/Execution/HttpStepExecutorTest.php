<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Runner\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Protocol\HttpStepExecutor;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Psr\Http\Message\ResponseInterface;

class HttpStepExecutorMockEngine implements EvaluationEngineInterface
{
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        return $expression->raw;
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
    {
        return null;
    }

    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
    {
        return null;
    }

    public function supportedXPathVersions(): array
    {
        return [];
    }

    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
    {
        return $value;
    }

    public function resolveValue(mixed $value, WorkflowContextInterface $context, ?string $stepId = null): mixed
    {
        if (is_string($value)) {
            return $this->interpolate($value, $context, $stepId ?? '');
        }

        if ($value instanceof Expression) {
            return $this->evaluate($value, new EvaluationContext($context, $stepId));
        }

        if ($value instanceof Selector) {
            return $this->evaluateSelector($value, $context, $stepId ?? '');
        }

        return $value;
    }

    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
    {
        return $body;
    }

    public function jsonPath(string $expression, array|object $data): mixed
    {
        return null;
    }

    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return null;
    }
}

class HttpStepExecutorMockOpenApiExecutor implements OpenApiExecutorInterface
{
    public function __construct(private ResponseInterface $response) {}

    public function execute(
        OpenApiOperationHandle $resolvedOperation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        if ($requestInterceptor) {
            $requestInterceptor(new Request('GET', 'http://localhost/thing'));
        }

        return $this->response;
    }
}

function createMockDocumentResolver(): OpenApiOperationResolver
{
    $mock = Mockery::mock(OpenApiOperationResolver::class);
    $mock->shouldReceive('resolve')->andReturn(new OpenApiOperationHandle(
        new ResolvedOperation(
            source: new SourceDescription('test-src', 'http://example.com/openapi.json', SourceType::Openapi),
            normalized: new NormalizedOpenApiOperation('/rides', 'get', null, [], [], [], [], [], []),
            rpcProtocol: null,
            operationName: null,
            rpcMethod: null,
            graphqlOperation: null,
            interaction: null,
        ),
        new OpenApi([]),
        new Operation([]),
    ));

    return $mock;
}

function createTestDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, 'desc', '1.0.0'),
        sourceDescriptions: [new SourceDescription('test-src', 'http://example.com/openapi.json', SourceType::Openapi)],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function httpStepExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [new SourceDescription('test', 'test.json', SourceType::Openapi)],
        [],
        new Components([], [], [], []),
        [],
    );
}

it('supports a step with no action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockOpenApiExecutor(new Response(200)), createMockDocumentResolver(), new HttpStepExecutorMockEngine(), Mockery::mock(StepOutputExtractor::class), Mockery::mock(ResponseValidatorInterface::class));
    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeTrue();
});

it('does not support a step with an action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockOpenApiExecutor(new Response(200)), createMockDocumentResolver(), new HttpStepExecutorMockEngine(), Mockery::mock(StepOutputExtractor::class), Mockery::mock(ResponseValidatorInterface::class));
    $step = new Step('s1', null, new StepTarget(action: 'send'), new StepFlow(), new StepIo());

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeFalse();
});

it('executes the request and returns a resolved outcome with statusCode/outputs/body', function (): void {
    $response = new Response(201, [], json_encode(['id' => 42]));
    $openApiExecutor = new HttpStepExecutorMockOpenApiExecutor($response);
    $outputExtractor = Mockery::mock(StepOutputExtractor::class);
    $outputExtractor->shouldReceive('extractOutputs')->andReturnUsing(function (Step $step, WorkflowContextInterface $context): array {
        return ['echoedBody' => $context->getSteps()[$step->stepId]['response']['body'] ?? null];
    });
    $executor = new HttpStepExecutor($openApiExecutor, createMockDocumentResolver(), new HttpStepExecutorMockEngine(), $outputExtractor, Mockery::mock(ResponseValidatorInterface::class));

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(201);
    expect($outcome->responseBody)->toBe(['id' => 42]);
    expect($outcome->outputs)->toBe(['echoedBody' => ['id' => 42]]);
});

it('stores the response on the context before calling extractOutputs, fixing the stale-context ordering bug', function (): void {
    $response = new Response(200, [], json_encode(['x' => 1]));
    $openApiExecutor = new HttpStepExecutorMockOpenApiExecutor($response);
    $lastContext = null;
    $outputExtractor = Mockery::mock(StepOutputExtractor::class);
    $outputExtractor->shouldReceive('extractOutputs')->andReturnUsing(function (Step $step, WorkflowContextInterface $context) use (&$lastContext): array {
        $lastContext = $context;

        return [];
    });
    $executor = new HttpStepExecutor($openApiExecutor, createMockDocumentResolver(), new HttpStepExecutorMockEngine(), $outputExtractor, Mockery::mock(ResponseValidatorInterface::class));

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $context = new WorkflowContext('def_1');

    $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($lastContext->getSteps()['s1']['response']['body'])->toBe(['x' => 1]);
});

it('validates response schema and fails fast on failure', function (): void {
    $validator = Mockery::mock(ResponseValidatorInterface::class);
    $validator->shouldReceive('validateResponseSchema')->once()->andThrow(
        new SchemaValidationException('sync-step', [['path' => '/', 'message' => 'bad schema']]),
    );
    $outputExtractor = Mockery::mock(StepOutputExtractor::class);
    $outputExtractor->shouldReceive('extractOutputs')->never();

    $openApiExecutor = Mockery::mock(OpenApiExecutorInterface::class);
    $openApiExecutor->shouldReceive('execute')->andReturnUsing(function ($resolved, $payload, $interceptor) {
        if ($interceptor) {
            $interceptor(new Request('GET', '/'));
        }

        return new Response(200, [], '{"bad": true}');
    });

    $executor = new HttpStepExecutor($openApiExecutor, createMockDocumentResolver(), new EvaluationEngine(expression: new ExpressionEngine()), $outputExtractor, $validator, true); // strict default
    $step = StepFactory::http(
        stepId: 'sync-step',
        description: null,
        flow: new StepFlow(strictValidation: true),
        io: new StepIo(),
        operationId: 'op',
    );

    $document = httpStepExecutorDocument();

    try {
        $executor->execute($step, new WorkflowContext('wf_1'), $document, 'exec_1');
        $this->fail('Expected exception');
    } catch (SchemaValidationException $e) {
        expect($e->stepId)->toBe('sync-step');
    }
});
