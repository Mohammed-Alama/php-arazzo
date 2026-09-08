<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Assembles the runner execution graph.
 *
 * The runner consumes the document package through its public face: source
 * resolution/fetching, preflight validation and OpenAPI operation resolution
 * all flow through {@see DocumentInterface}. Expression services flow
 * through the injected expression public face ({@see ExpressionEngineInterface}),
 * with the runner composing output extraction and schema validation itself.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExecutionGraphFactory
{
    public function __construct(
        private readonly DocumentInterface $documents,
        private readonly ExpressionEngineInterface $engine,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    public function createWorkflowExecutor(): WorkflowExecutor
    {
        $client = $this->httpClient ?? new Client();
        $factory = $this->requestFactory ?? new HttpFactory();

        $expressionResolver = new ExecutionExpressionResolver(
            $this->engine,
            new StepOutputExtractor($this->documents, $this->engine),
            new ResponseSchemaValidator($this->documents),
        );

        return new WorkflowExecutor(
            new StepExecutor(
                new DefaultOpenApiExecutor($client, $factory),
                $expressionResolver,
                $this->documents,
                engine: $this->engine,
            ),
            workflowEngine: new WorkflowEngine($expressionResolver),
            preflight: $this->documents,
        );
    }
}
