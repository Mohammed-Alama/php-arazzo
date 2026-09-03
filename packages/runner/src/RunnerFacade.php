<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Document\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Document\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Document\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\Evaluation\ExpressionResolver;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

final class RunnerFacade implements RunnerFacadeInterface
{
    private WorkflowExecutor $executor;

    public function __construct(?ClientInterface $httpClient = null, private readonly ?PreflightValidator $preflight = null)
    {
        $client = $httpClient ?? new Client();
        $factory = new HttpFactory();
        $httpFetcher = new HttpFetcher($client, $factory);

        $registry = new SourceRegistry(new DefaultSourceResolver([
            'http' => $httpFetcher,
            'https' => $httpFetcher,
            'file' => new LocalFetcher(),
        ]));

        $evaluator = new ExpressionEvaluator();
        $operationResolver = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($registry),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );
        $resolver = new ExpressionResolver(
            $evaluator,
            new StepOutputExtractor($operationResolver, $evaluator),
            new CriteriaEvaluator($evaluator),
            new ResponseSchemaValidator($operationResolver),
        );

        $this->executor = new WorkflowExecutor(
            new StepExecutor(
                new DefaultOpenApiExecutor($client, $factory),
                $resolver,
                $operationResolver,
            ),
            workflowEngine: new WorkflowEngine($resolver),
            preflight: $this->preflight ?? $this->defaultPreflight($registry, $operationResolver),
        );
    }

    public function run(ArazzoDocument $document, string $workflowId, array $inputs = []): array
    {
        $workflow = null;

        foreach ($document->workflows as $candidate) {
            if ($candidate->workflowId === $workflowId) {
                $workflow = $candidate;
                break;
            }
        }

        if ($workflow === null) {
            throw new RuntimeException(sprintf("unknown workflow '%s'", $workflowId));
        }

        $result = $this->executor->execute($workflow, $document, $inputs);

        return [
            'workflowId' => $result->workflowId,
            'status' => $result->status,
            'outputs' => $result->outputs,
            'stepsSpent' => $result->stepsSpent,
            'workflowCallStack' => $result->workflowCallStack,
        ];
    }

    private function defaultPreflight(SourceRegistry $registry, OpenApiOperationResolver $operationResolver): PreflightValidator
    {
        return new PreflightValidator($registry, $operationResolver, new DomXpathEvaluator());
    }
}
