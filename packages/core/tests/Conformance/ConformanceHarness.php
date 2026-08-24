<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Events\RunCompleted;
use Alama\Arazzo\Runner\Events\RunFailed;
use Alama\Arazzo\Runner\Events\RunStarted;
use Alama\Arazzo\Runner\Events\StepExecuted;
use Alama\Arazzo\Runner\Events\StepFailed;
use Alama\Arazzo\Runner\Events\StepStarted;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Spec\SourceDocument;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use Alama\Arazzo\Tests\Support\RecordingEventDispatcher;
use GuzzleHttp\Psr7\Response;
use RuntimeException;

/**
 * Shared harness for conformance fixtures: parses the fixture document,
 * seeds inline sources, scripts the fake HTTP transport, and normalizes
 * the observable event stream into an adapter-independent summary that
 * synchronous and queued adapters must produce identically.
 */
abstract class ConformanceHarness
{
    protected RecordingEventDispatcher $events;

    protected FakePsr18Client $http;

    protected SourceRegistry $sourceRegistry;

    /**
     * @param array<string, mixed> $fixture
     *
     * @return array<string, mixed>
     */
    abstract public function run(array $fixture): array;

    /**
     * @param array<string, mixed> $fixture
     */
    protected function prepare(array $fixture): ArazzoDocument
    {
        $this->events = new RecordingEventDispatcher();
        $this->http = new FakePsr18Client();
        $this->sourceRegistry = new SourceRegistry(new DefaultSourceResolver([]));

        // Transport failures replay before any scripted response. Each entry
        // is either a message string or {message, times} for repeated faults
        // (e.g. exhausting a retry ceiling).
        foreach ($fixture['transportErrors'] ?? [] as $fault) {
            $message = is_array($fault) ? (string) ($fault['message'] ?? 'transport error') : (string) $fault;
            $times = is_array($fault) ? (int) ($fault['times'] ?? 1) : 1;

            for ($i = 0; $i < $times; $i++) {
                $this->http->failWith(new RuntimeException($message));
            }
        }

        foreach ($fixture['responses'] ?? [] as $response) {
            $this->http->enqueue(new Response(
                (int) ($response['status'] ?? 200),
                $response['headers'] ?? ['Content-Type' => 'application/json'],
                json_encode($response['body'] ?? new \stdClass()),
            ));
        }

        $document = (new Parser())->parse(new RawDocument(
            $fixture['arazzo'],
            'memory://conformance/' . ($fixture['name'] ?? 'fixture') . '.json',
            Format::Json,
        ));

        foreach ($fixture['sources'] ?? [] as $name => $content) {
            $this->sourceRegistry->register(new SourceDocument(
                (string) $name,
                SourceType::Openapi,
                'https://conformance.invalid/' . $name . '.json',
                $content,
            ));
        }

        return $document;
    }

    protected function resolver(OpenApiOperationResolver $operationResolver): ExpressionResolverInterface
    {
        $evaluator = new ExpressionEvaluator();

        return new ArazzoExpressionResolver(
            $evaluator,
            new ArazzoOutputExtractor($operationResolver, $evaluator),
            new ArazzoCriteriaEvaluator($evaluator),
            new ArazzoSchemaValidator($operationResolver),
        );
    }

    protected function operationResolver(SourceRegistry $registry): OpenApiOperationResolver
    {
        return new OpenApiOperationResolver(
            new OpenApiDocumentLoader($registry),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );
    }

    /**
     * Normalizes the recorded event stream plus HTTP traffic into the
     * canonical observation shape used by fixture and parity tests.
     *
     * @return array<string, mixed>
     */
    protected function observe(): array
    {
        $status = '';
        $outputs = [];
        $attempts = [];
        $order = [];
        $lastAttemptFailed = [];
        $failureMessages = [];
        $errors = [];
        $eventClasses = [];

        foreach ($this->events->events as $event) {
            if ($event instanceof RunStarted) {
                continue;
            }
            $eventClasses[] = $event::class;

            if ($event instanceof RunCompleted) {
                $status = 'succeeded';
                $outputs = $event->outputs;
            } elseif ($event instanceof RunFailed) {
                $status = 'failed';
                $errors[] = $event->cause->getMessage();
            } elseif ($event instanceof StepStarted) {
                $attempts[$event->stepId] = ($attempts[$event->stepId] ?? 0) + 1;

                if (!in_array($event->stepId, $order, true)) {
                    $order[] = $event->stepId;
                }

                // Each new attempt resets the step's provisional outcome.
                unset($lastAttemptFailed[$event->stepId]);
            } elseif ($event instanceof StepFailed) {
                $lastAttemptFailed[$event->stepId] = true;
                $failureMessages[$event->stepId][] = $event->cause->getMessage();
            } elseif ($event instanceof StepExecuted && !($event->criteriaMet)) {
                $lastAttemptFailed[$event->stepId] = true;
            }
        }

        $steps = [];
        $retried = [];

        foreach ($order as $stepId) {
            $finallyFailed = $lastAttemptFailed[$stepId] ?? false;
            $steps[] = [
                'stepId' => $stepId,
                'status' => $finallyFailed ? 'failure' : 'success',
                'attempts' => $attempts[$stepId] ?? 1,
            ];

            if ($finallyFailed) {
                $errors = array_merge($errors, $failureMessages[$stepId] ?? []);
            }

            if (($attempts[$stepId] ?? 1) > 1) {
                $retried[$stepId] = $attempts[$stepId] - 1;
            }
        }

        return [
            'status' => $status,
            'steps' => $steps,
            'outputs' => $outputs,
            'requests' => array_map(
                fn ($request): string => $request->getMethod() . ' ' . $request->getUri(),
                $this->http->requests,
            ),
            'requestHeaders' => $this->http->requests === [] ? [] : array_map(
                fn (array $values): string => $values[0],
                $this->http->requests[0]->getHeaders(),
            ),
            'errors' => array_values($errors),
            'events' => $eventClasses,
            'retries' => $retried,
        ];
    }
}
