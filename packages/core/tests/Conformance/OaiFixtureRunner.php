<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Runs a corpus document (by file path) through the synchronous conformance
 * stack, pre-seeding the locally-vendored companion sources so nothing
 * touches the network.
 */
final class OaiFixtureRunner extends ConformanceHarness
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function run(array $fixture): array
    {
        $path = (string) $fixture['arazzoFile'];
        $decoder = new SymfonyYamlDecoder();
        $decoded = $decoder->decode((string) file_get_contents($path));

        // Synthesize the harness fixture shape with vendored companions.
        $sources = [];

        foreach (OaiCorpusRunner::localSources($path) as $name => $sourceDocument) {
            $sources[$name] = $sourceDocument->content;
        }

        $inputs = $fixture['inputs'] ?? [];

        if ($inputs === []) {
            $schema = is_array($decoded['workflows'][0]['inputs'] ?? null) ? $decoded['workflows'][0]['inputs'] : [];

            if ($schema !== []) {
                $inputs = ConformanceFabricator::objectFromSchema($schema);
            }
        }

        $document = $this->prepare([
            'name' => (string) ($fixture['name'] ?? basename($path)),
            'arazzo' => $decoded,
            'sources' => $sources,
            'responses' => $fixture['responses'] ?? [],
            'inputs' => $inputs,
        ]);

        if (($document->workflows[0] ?? null) === null) {
            throw new \InvalidArgumentException('Document has no workflows');
        }

        $operationResolver = $this->operationResolver($this->sourceRegistry);

        $executor = new WorkflowExecutor(
            new StepExecutor(
                new FakerOpenApiExecutor(
                    new DefaultOpenApiExecutor($this->http, new HttpFactory()),
                    FakerOpenApiExecutor::referencedBodyFields((string) file_get_contents($path)),
                ),
                $this->resolver($operationResolver),
                $operationResolver,
            ),
            new WorkflowEngine($this->resolver($operationResolver)),
            events: $this->events,
        );

        try {
            $executor->execute($document->workflows[0], $document, $fixture['inputs'] ?? []);
        } catch (\Throwable) {
            // Observed through the event stream.
        }

        return $this->observe();
    }
}
