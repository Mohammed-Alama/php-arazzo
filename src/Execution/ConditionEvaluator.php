<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Expression;
use InvalidArgumentException;

class ConditionEvaluator
{
    public function __construct(private ExpressionEvaluator $evaluator)
    {
    }

    public function evaluate(string $condition, string $stepId, VariableContext $context): bool
    {
        // Simple condition parser: $expression == value, $expression != value
        if (preg_match('/^(\S+)\s*(==|!=|matches)\s*(.+)$/', trim($condition), $matches)) {
            $exprString = $matches[1];
            $operator = $matches[2];
            $expectedValue = $matches[3];

            // Resolve bare variables
            if (str_starts_with($exprString, '$response')) {
                $exprString = str_replace('$response', '$steps.' . $stepId . '.response', $exprString);
            } elseif (str_starts_with($exprString, '$request')) {
                $exprString = str_replace('$request', '$steps.' . $stepId . '.request', $exprString);
            } elseif (str_starts_with($exprString, '$statusCode')) {
                $exprString = str_replace('$statusCode', '$steps.' . $stepId . '.response.statusCode', $exprString);
            } elseif (str_starts_with($exprString, '$method')) {
                $exprString = str_replace('$method', '$steps.' . $stepId . '.request.method', $exprString);
            } elseif (str_starts_with($exprString, '$url')) {
                $exprString = str_replace('$url', '$steps.' . $stepId . '.request.url', $exprString);
            }

            // Wrap in {$} if not already wrapped
            if (!str_starts_with($exprString, '{$')) {
                $exprString = '{$' . ltrim($exprString, '$') . '}';
            }

            $actualValue = $this->evaluator->evaluate(new Expression($exprString), $context);

            // Normalize expected value
            $expectedValue = trim($expectedValue, " '\"");
            if (is_numeric($expectedValue)) {
                // handle numbers
                $expectedValue = str_contains($expectedValue, '.') ? (float) $expectedValue : (int) $expectedValue;
            }

            return match ($operator) {
                '==' => $actualValue == $expectedValue,
                '!=' => $actualValue != $expectedValue,
                'matches' => (bool) preg_match('/' . str_replace('/', '\/', $expectedValue) . '/', (string) $actualValue),
                default => false,
            };
        }

        throw new InvalidArgumentException("Unsupported condition format: {$condition}");
    }
}
