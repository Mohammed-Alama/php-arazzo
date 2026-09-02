<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

use Alama\Arazzo\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Evaluation\Enum\ComparisonOperator;
use Alama\Arazzo\Evaluation\Enum\LogicalOperator;
use Alama\Arazzo\Evaluation\Interfaces\ConditionNode;
use Alama\Arazzo\Expression\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;

final class ConditionEvaluator
{
    private Lexer $lexer;

    private Parser $parser;

    public function __construct(
        private ExpressionEvaluatorInterface $evaluator,
    ) {
        $this->lexer = new Lexer();
        $this->parser = new Parser($this->lexer);
    }

    public function evaluate(string $condition, WorkflowContextInterface $context, ?string $stepId = null, ?ArazzoDocument $document = null): bool
    {
        $ast = $this->parser->parse($condition);
        $evaluationContext = new EvaluationContext($context, $stepId, $document);

        return (bool) $this->resolve($ast, $evaluationContext);
    }

    private function resolve(ConditionNode $node, EvaluationContext $context): bool
    {
        if ($node instanceof Ast\LogicalOp) {
            $left = $this->resolve($node->left, $context);

            return match ($node->op) {
                LogicalOperator::And => $left && $this->resolve($node->right, $context),
                LogicalOperator::Or => $left || $this->resolve($node->right, $context),
            };
        }

        if ($node instanceof Ast\UnaryNot) {
            return !$this->resolve($node->operand, $context);
        }

        if ($node instanceof Ast\Comparison) {
            return $this->compare(
                $node,
                $this->operandValue($node->left, $context),
                $this->operandValue($node->right, $context),
            );
        }

        if ($node instanceof Ast\Literal) {
            return self::truthy($node->value);
        }

        if ($node instanceof Ast\RuntimeExpr) {
            return self::truthy($this->evaluator->evaluate($node->expression, $context));
        }

        throw new ConditionSyntaxException('Unsupported condition node.');
    }

    /**
     * A comparison side is normally a literal or runtime expression; a nested
     * logical node contributes its boolean result.
     */
    private function operandValue(ConditionNode $operand, EvaluationContext $context): mixed
    {
        if ($operand instanceof Ast\Literal) {
            return $operand->value;
        }

        if ($operand instanceof Ast\RuntimeExpr) {
            return $this->evaluator->evaluate($operand->expression, $context);
        }

        return $this->resolve($operand, $context);
    }

    private function compare(Ast\Comparison $comparison, mixed $left, mixed $right): bool
    {
        if ($comparison->op === ComparisonOperator::Eq || $comparison->op === ComparisonOperator::Neq) {
            $equal = $this->looseEquals($left, $right);

            return $comparison->op === ComparisonOperator::Eq ? $equal : !$equal;
        }

        $a = self::asNumber($left);
        $b = self::asNumber($right);
        if ($a === null || $b === null) {
            return false;
        }

        return match ($comparison->op) {
            ComparisonOperator::Gt => $a > $b,
            ComparisonOperator::Gte => $a >= $b,
            ComparisonOperator::Lt => $a < $b,
            ComparisonOperator::Lte => $a <= $b,
        };
    }

    private function looseEquals(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        if (is_bool($left) || is_bool($right)) {
            return is_bool($left) === is_bool($right) && $left === $right;
        }

        $a = self::asNumber($left);
        $b = self::asNumber($right);
        if ($a !== null && $b !== null) {
            return $a === $b;
        }

        if (is_array($left) || is_array($right)) {
            return $left === $right;
        }

        return strcasecmp(self::stringify($left), self::stringify($right)) === 0;
    }

    private static function asNumber(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', trim($value)) === 1) {
            return str_contains(trim($value), '.') ? (float) $value : (int) $value;
        }

        return null;
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function truthy(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        if ($value === '' || $value === 0 || $value === 0.0 || $value === '0') {
            return false;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }
}
