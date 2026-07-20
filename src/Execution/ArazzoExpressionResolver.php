<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class ArazzoExpressionResolver implements ExpressionResolverInterface
{
    public function __construct(
        private ExpressionEvaluator $runtimeEvaluator,
    ) {
    }

    public function compileRequest(Step $step, WorkflowContext $context): RequestInterface
    {
        // Actually it's $step->operationPath in our schema (from Arazzo 1.0.0 specs)
        // But for MVP if there's an operationId or we just need a URI. Let's assume operationPath maps to it.
        // Wait, the Arazzo spec uses `operationId` or `operationPath`.
        // Let's use $step->operationPath if available.
        $uri = $step->operationPath ?? 'http://localhost';

        // Method would come from the operation or parameters, hardcoded to GET for this simple MVP.
        $method = 'GET';

        return new Request($method, $uri);
    }

    public function extractOutputs(Step $step, array $responseData): array
    {
        $outputs = [];
        foreach ($step->outputs ?? [] as $outputName => $expressionObj) {
            $expressionStr = $expressionObj->raw;
            // Check if it's a JSONPath (starts with $.)
            if (str_starts_with($expressionStr, '$')) {
                // Remove whitespace just in case
                $expressionStr = trim($expressionStr);
                $outputs[$outputName] = JsonPathEvaluator::evaluate($expressionStr, $responseData);
            } else {
                $outputs[$outputName] = $expressionStr; // Literal fallback
            }
        }

        return $outputs;
    }
}
