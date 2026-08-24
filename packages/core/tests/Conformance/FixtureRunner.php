<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Events\RunStarted;
use Alama\Arazzo\Runner\Events\StepStarted;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\ExecutionResult;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Spec\SourceDocument;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use Alama\Arazzo\Tests\Support\RecordingEventDispatcher;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

/**
 * Executes a golden conformance fixture through the synchronous adapter
 * and returns normalized, adapter-independent observations.
 *
 * Fixture shape:
 *   name      - fixture label
 *   arazzo    - raw Arazko document (array)
 *   sources   - map of sourceName => inline OpenAPI document (array)
 *   responses - scripted HTTP responses in order ({status, headers, body})
 *   inputs    - workflow inputs
 */
final class FixtureRunner
{
    private RecordingEventDispatcher $events;

    private FakePsr18Client $http;

    /**
     * @param array<string, mixed> $fixture
     *
     * @return array<string, mixed>
     */
    public function run(array $fixture): array
    {
        $this->events = new RecordingEventDispatcher();
        $this->http = new FakePsr18Client();

        foreach ($fixture['responses'] ?? [] as $response) {
            $this->http->enqueue(new Response(
                (int) ($response['status'] ?? 200),
                $response['headers'] ?? ['Content-Type' => 'application/json'],
                json_encode($response['body'] ?? new stdClass()),
            ));
        }

        $document = (new Parser())->parse(new RawDocument(
            $fixture['arazzo'],
            'memory://conformance/' . ($fixture['name'] ?? 'fixture') . '.json',
            Format::Json,
        ));

        $registry = new SourceRegistry(new DefaultSourceResolver([]));

        foreach ($fixture['sources'] ?? [] as $name => $content) {
            $registry->register(new SourceDocument(
                (string) $name,
                SourceType::Openapi,
                'https://conformance.invalid/' . $name . '.json',
                $content,
            ));
        }

        $evaluator = new ExpressionEvaluator();
        $operationResolver = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($registry),
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

        $executor = new WorkflowExecutor(
            new StepExecutor(
                new DefaultOpenApiExecutor($this->http, new HttpFactory()),
                $resolver,
                $operationResolver,
            ),
            new WorkflowEngine($resolver),
            events: $this->events,
        );

        $workflow = $document->workflows[0] ?? null;

        if ($workflow === null) {
            throw new InvalidArgumentException('Fixture has no workflows');
        }

        return $this->normalize(
            $executor->execute($workflow, $document, $fixture['inputs'] ?? []),
            $this->events,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(ExecutionResult $result, RecordingEventDispatcher $events): array
    {
        $attempts = [];
        $eventClasses = [RunStarted::class];

        foreach ($events->events as $event) {
            if ($event instanceof StepStarted) {
                $attempts[$event->stepId] = ($attempts[$event->stepId] ?? 0) + 1;
            }

            if (!$event instanceof RunStarted) {
                $eventClasses[] = $event::class;
            }
        }

        $steps = [];
        $retried = [];

        foreach ($result->stepResults as $stepId => $stepResult) {
            $attemptCount = $attempts[$stepId] ?? 1;
            $steps[] = [
                'stepId' => $stepId,
                'status' => $stepResult->success ? 'success' : 'failure',
                'attempts' => $attemptCount,
            ];

            if ($attemptCount > 1) {
                $retried[$stepId] = $attemptCount - 1;
            }
        }

        return [
            'status' => $result->status,
            'steps' => $steps,
            'outputs' => $result->outputs,
            'requests' => array_map(
                fn ($request): string => $request->getMethod() . ' ' . $request->getUri(),
                $this->http->requests,
            ),
            'requestHeaders' => $this->http->requests === [] ? [] : array_map(
                fn (array $values): string => $values[0],
                $this->http->requests[0]->getHeaders(),
            ),
            'errors' => array_values(array_map(
                fn ($stepResult): string => $stepResult->error?->getMessage() ?? '',
                array_filter($result->stepResults, fn ($stepResult): bool => !$stepResult->success),
            )),
            'events' => $eventClasses,
            'retries' => $retried,
        ];
    }
}
