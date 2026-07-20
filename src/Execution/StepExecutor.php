<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Dto\StepResult;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;

class StepExecutor
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ExpressionEvaluator $evaluator
    ) {}

    public function execute(Step $step, VariableContext $context): StepResult
    {
        // 1. Resolve source for operation
        // For MVP, we will assume operation details are extracted manually or via helper
        // but since we don't have full OpenAPI traversing here, we'll build a dummy PSR-7 request
        // or actually try to parse it if we can.
        
        // Resolving parameters
        $queryParams = [];
        $headers = [];
        $pathReplacements = [];
        $body = [];

        foreach ($step->parameters as $param) {
            $val = $param->value instanceof \Alama\LaravelArazzo\Dto\Expression 
                ? $this->evaluator->evaluate($param->value) 
                : $param->value;
            
            if ($param->in === 'query') {
                $queryParams[$param->name] = $val;
            } elseif ($param->in === 'header') {
                $headers[$param->name] = $val;
            } elseif ($param->in === 'path') {
                $pathReplacements[$param->name] = $val;
            }
        }

        // 2. Build and Send Request
        // In a real implementation we lookup the operation to get method & URL.
        // For now, we mock the execution steps.
        $requestData = [
            'query' => $queryParams,
            'headers' => $headers,
            'path' => $pathReplacements,
            'body' => $body,
        ];
        
        $context->setStepRequest($step->stepId, $requestData);
        
        // Mocking PSR-7 Request logic ...
        // $request = $this->requestFactory->createRequest('GET', 'http://localhost');
        // $response = $this->httpClient->sendRequest($request);
        
        $responseData = [
            'statusCode' => 200,
            'headers' => [],
            'body' => [],
        ];
        $context->setStepResponse($step->stepId, $responseData);
        
        // 3. Evaluate Outputs
        $outputs = [];
        foreach ($step->outputs as $key => $expr) {
            $outputs[$key] = $this->evaluator->evaluate($expr);
            $context->setStepOutput($step->stepId, $key, $outputs[$key]);
        }
        
        // 4. Evaluate Success Criteria
        $success = true;
        foreach ($step->successCriteria as $criterion) {
            $conditionValue = $this->evaluator->evaluate($criterion->condition);
            // evaluate regex or true/false
            if ($conditionValue === false) {
                $success = false;
            }
        }
        
        return new StepResult($step->stepId, $success, $outputs);
    }
}
