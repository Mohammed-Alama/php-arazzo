<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\Evaluation\ExpressionResolver;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Assembles the runner execution graph.
 *
 * The runner consumes the document package through its public face: source
 * resolution/fetching, preflight validation and OpenAPI operation resolution
 * all flow through {@see DocumentInterface}. The remaining expression
 * services are composed here until the internals migrate to the expression
 * public face.
 */
final class ExecutionGraphFactory
{
    public function __construct(
        private readonly DocumentInterface $documents,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    public function createWorkflowExecutor(): WorkflowExecutor
    {
        $client = $this->httpClient ?? new Client();
        $factory = $this->requestFactory ?? new HttpFactory();

        $evaluator = new ExpressionEvaluator();
        $expressionResolver = new ExpressionResolver(
            $evaluator,
            new StepOutputExtractor($this->documents, $evaluator),
            new CriteriaEvaluator($evaluator),
            new ResponseSchemaValidator($this->documents),
        );

        return new WorkflowExecutor(
            new StepExecutor(
                new DefaultOpenApiExecutor($client, $factory),
                $expressionResolver,
                $this->documents,
            ),
            workflowEngine: new WorkflowEngine($expressionResolver),
            preflight: $this->documents,
        );
    }
}
