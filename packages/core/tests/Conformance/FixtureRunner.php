<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Executes a fixture through the SYNCHRONOUS adapter (WorkflowExecutor
 * driving every step in-process).
 *
 * Fixture shape:
 *   name      - fixture label
 *   arazzo    - raw Arazko document (array)
 *   sources   - map of sourceName => inline OpenAPI document (array)
 *   responses - scripted HTTP responses in order ({status, headers, body})
 *   inputs    - workflow inputs
 */
final class FixtureRunner extends ConformanceHarness
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function run(array $fixture): array
    {
        $document = $this->prepare($fixture);

        if (($document->workflows[0] ?? null) === null) {
            throw new \InvalidArgumentException('Fixture has no workflows');
        }

        $operationResolver = $this->operationResolver($this->sourceRegistry);

        $executor = new WorkflowExecutor(
            new StepExecutor(
                new DefaultOpenApiExecutor($this->http, new HttpFactory()),
                $this->resolver($operationResolver),
                $operationResolver,
            ),
            new WorkflowEngine($this->resolver($operationResolver)),
            events: $this->events,
        );

        try {
            $executor->execute($document->workflows[0], $document, $fixture['inputs'] ?? []);
        } catch (\Throwable) {
            // Terminal failures are observed through the event stream.
        }

        return $this->observe();
    }
}
