<?php

declare(strict_types=1);

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Events\RunFailed;
use Alama\Arazzo\Events\StepFailed;
use Alama\Arazzo\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Execution\HttpStepExecutor;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Lexer;
use Alama\Arazzo\Resolver\Exceptions\UnresolvableReferenceException;
use Alama\Arazzo\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Tests\Conformance\ConformanceHarness;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use Alama\Arazzo\Tests\Support\RecordingEventDispatcher;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

require_once __DIR__.'/HttpStepExecutorTest.php';

/**
 * Anonymous harness exposing the protected conformance plumbing so the
 * classification tests can drive the REAL pipeline end-to-end.
 */
$classificationHarness = new class() extends ConformanceHarness
{
    public function run(array $fixture): array
    {
        $this->prepare($fixture);

        return [];
    }

    public function boot(array $fixture): ArazzoDocument
    {
        return $this->prepare($fixture);
    }

    public function ops(): OpenApiOperationResolver
    {
        return $this->operationResolver($this->sourceRegistry);
    }

    public function res(OpenApiOperationResolver $r): ExpressionResolverInterface
    {
        return $this->resolver($r);
    }

    public function ev(): RecordingEventDispatcher
    {
        return $this->events;
    }

    public function client(): FakePsr18Client
    {
        return $this->http;
    }
};

function requestConstructionFixture(): array
{
    return json_decode((string) file_get_contents(
        __DIR__.'/../Conformance/fixtures/openapi-request-construction.json',
    ), true, 512, JSON_THROW_ON_ERROR);
}

it('carries the offending expression and offset on syntax errors', function (): void {
    try {
        (new Lexer())->tokenize('{unterminated');
        $this->fail('expected ExpressionSyntaxException');
    } catch (ExpressionSyntaxException $e) {
        expect($e->expression)->toBe('{unterminated')
            ->and($e->offset)->toBeGreaterThanOrEqual(0)
            ->and($e->codeId)->toBe('expr.syntax');
    }
});

it('names the source on unresolvable circular source references', function (): void {
    $e = new UnresolvableReferenceException('Circular reference detected when resolving source "api"', 'api');

    expect($e->sourceName)->toBe('api');
});

it('preserves raw body, content type, and transport category on synthetic failures', function () use ($classificationHarness): void {
    $fixture = requestConstructionFixture();
    $document = $classificationHarness->boot($fixture);
    $operationResolver = $classificationHarness->ops();
    $resolver = $classificationHarness->res($operationResolver);

    $executor = new HttpStepExecutor(
        new DefaultOpenApiExecutor($classificationHarness->client(), new HttpFactory()),
        $resolver,
        $operationResolver,
    );

    $step = $document->workflows[0]->steps[0];
    $context = new WorkflowContext(
        definitionId: $document->workflows[0]->workflowId,
        inputs: $fixture['inputs'],
        workflowId: $document->workflows[0]->workflowId,
        executionId: 'exec_classification',
    );

    // 1. Transport failure -> synthetic 500 tagged as transport.
    // (own client so the scripted fixture response cannot be consumed first)
    $failingHttp = new FakePsr18Client();
    $failingHttp->failWith(new RuntimeException('connection refused'));

    $executor = new HttpStepExecutor(
        new DefaultOpenApiExecutor($failingHttp, new HttpFactory()),
        $resolver,
        $operationResolver,
    );

    $outcome = $executor->execute($step, $context, $document, 'exec_classification');

    expect($outcome->statusCode)->toBe(500)
        ->and($outcome->failureCategory)->toBe('transport')
        ->and($outcome->responseBody['error'] ?? null)->toBe('connection refused');

    // 2. Successful response retains the raw payload and content type.
    $ok = json_decode((string) file_get_contents(__DIR__.'/../Conformance/fixtures/goto-on-failure.json'), true);
    expect($ok)->not->toBeNull();

    $http2 = new FakePsr18Client();
    $http2->enqueue(new Response(200, ['Content-Type' => 'application/json'], '{"city":"paris"}'));

    $executor2 = new HttpStepExecutor(
        new DefaultOpenApiExecutor($http2, new HttpFactory()),
        $resolver,
        $operationResolver,
    );

    $outcome2 = $executor2->execute($step, $context, $document, 'exec_classification');

    expect($outcome2->rawBody)->toBe('{"city":"paris"}')
        ->and($outcome2->contentType)->toBe('application/json');
});

it('classifies unmet-criteria failures on step events while keeping execution faults distinct', function () use ($classificationHarness): void {
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../Conformance/fixtures/goto-on-failure.json'), true);
    $document = $classificationHarness->boot($fixture);
    $operationResolver = $classificationHarness->ops();
    $resolver = $classificationHarness->res($operationResolver);

    foreach ($fixture['responses'] as $response) {
        $classificationHarness->client()->enqueue(new Response((int) $response['status'], [], json_encode($response['body'] ?? new stdClass())));
    }

    $executor = new WorkflowExecutor(
        new StepExecutor(
            new DefaultOpenApiExecutor($classificationHarness->client(), new HttpFactory()),
            $resolver,
            $operationResolver,
        ),
        new WorkflowEngine($resolver),
        events: $classificationHarness->ev(),
    );

    $result = $executor->execute($document->workflows[0], $document, []);

    $categories = [];
    foreach ($classificationHarness->ev()->events as $event) {
        if ($event instanceof StepFailed || $event instanceof RunFailed) {
            $categories[] = [$event::class, $event->category];
        }
    }

    // The goto-on-failure fixture recovers, so only a criteria step
    // failure is expected - never a run-level one.
    expect($result->status)->toBe('succeeded')
        ->and($categories)->toContain([StepFailed::class, 'criteria'])
        ->and($categories)->not->toContain([RunFailed::class, 'criteria'])
        // Defaults stay stable for consumers.
        ->and((new StepFailed('e', 'w', 's', new RuntimeException('x'), new DateTimeImmutable()))->category)->toBe('execution')
        ->and((new RunFailed('e', 'w', new RuntimeException('x'), new DateTimeImmutable()))->category)->toBe('execution');
});
