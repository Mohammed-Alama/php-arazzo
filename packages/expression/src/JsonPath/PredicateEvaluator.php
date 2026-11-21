<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

use Alama\Arazzo\Expression\Data\JsonPathBudget;

/**
 * Filter predicate evaluation (comparison, logical, exists, function).
 *
 * @internal stays out of the advertised contract; consumed by Rfc9535Engine.
 */
final class PredicateEvaluator
{
    public function __construct(
        private JsonPathBudget $budget,
        private JsonPathFunctions $functions,
    ) {}

    /**
     * @param  array<array-key, mixed>  $predicate
     * @return list<array<array-key, mixed>>
     */
    public function filter(array $predicate, mixed $node, int &$visited): array
    {
        if (!is_array($node)) {
            return [];
        }

        $out = [];

        // A JSON array (list) is filtered element-by-element (@ = each element);
        // a JSON object (associative array) is tested as a whole (@ = the object).
        $candidates = array_is_list($node) ? $node : [$node];

        foreach ($candidates as $item) {
            $this->budget->touchNode($visited);

            if ($this->evaluatePredicate($predicate, $item)) {
                $out[] = $item;
            }
        }

        /** @var list<array<array-key, mixed>>  $out */
        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $predicate
     */
    public function evaluatePredicate(array $predicate, mixed $item): bool
    {
        $kind = $predicate['kind'];

        /** @var string $operator */
        $operator = $predicate['operator'] ?? '';
        /** @var array<array-key, mixed>|null  $exprNode */
        $exprNode = $predicate['expression'] ?? null;
        /** @var string $name */
        $name = $predicate['name'] ?? '';
        /** @var list<array<array-key, mixed>>  $args */
        $args = $predicate['args'] ?? [];

        return match ($kind) {
            'comparison' => $this->compare(
                $predicate['left'] ?? null,
                $predicate['right'] ?? null,
                $operator,
                $item,
            ),
            'logical' => $this->evaluateLogical($predicate, $item),
            'exists' => $exprNode !== null && $this->functions->evaluateExists($exprNode, $item),
            'function' => (bool) $this->functions->evaluateFunction($name, $args, $item),
            default => false,
        };
    }

    private function evaluateLogical(array $predicate, mixed $item): bool
    {
        /** @var array<array-key, mixed>|null  $leftNode */
        $leftNode = $predicate['left'] ?? null;
        if ($leftNode === null) {
            return false;
        }
        $left = $this->evaluatePredicate($leftNode, $item);
        /** @var string $operator */
        $operator = $predicate['operator'] ?? '';

        if ($operator === '!') {
            return !$left;
        }

        /** @var array<array-key, mixed>|null  $rightNode */
        $rightNode = $predicate['right'] ?? null;
        if ($rightNode === null) {
            return false;
        }
        $right = $this->evaluatePredicate($rightNode, $item);

        return match ($operator) {
            '&&' => $left && $right,
            '||' => $left || $right,
            default => false,
        };
    }

    private function compare(mixed $left, mixed $right, string $operator, mixed $item): bool
    {
        $l = $this->functions->resolveValue($left, $item);
        $r = $this->functions->resolveValue($right, $item);

        return match ($operator) {
            '==' => $this->jsonEquals($l, $r),
            '!=' => !$this->jsonEquals($l, $r),
            '<' => $this->jsonLessThan($l, $r),
            '>' => $this->jsonLessThan($r, $l),
            '<=' => $this->jsonEquals($l, $r) || $this->jsonLessThan($l, $r),
            '>=' => $this->jsonEquals($l, $r) || $this->jsonLessThan($r, $l),
            '=~' => $this->matchRegex(
                is_string($l) ? $l : (is_numeric($l) ? (string) $l : ''),
                is_string($r) ? $r : (is_numeric($r) ? (string) $r : ''),
            ),
            default => false,
        };
    }

    /**
     * RFC 9535 equality: values of different JSON types are never equal;
     * within a type, compare by value (numbers compared numerically).
     */
    private function jsonEquals(mixed $l, mixed $r): bool
    {
        if ($this->jsonType($l) !== $this->jsonType($r)) {
            return false;
        }

        if ($this->jsonType($l) === 'number') {
            return $l == $r;
        }

        return $l === $r;
    }

    /**
     * RFC 9535 ordering: only defined within a single JSON type.
     */
    private function jsonLessThan(mixed $l, mixed $r): bool
    {
        if ($this->jsonType($l) !== $this->jsonType($r)) {
            return false;
        }

        if ($this->jsonType($l) === 'number') {
            return $l < $r;
        }

        if ($this->jsonType($l) === 'string') {
            return $l < $r;
        }

        if ($this->jsonType($l) === 'boolean') {
            return $l === false && $r === true;
        }

        return false;
    }

    /**
     * @return 'null'|'boolean'|'number'|'string'|'array'|'object'
     */
    private function jsonType(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value) || is_float($value)) {
            return 'number';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }

        return 'object';
    }

    private function matchRegex(string $haystack, string $pattern): bool
    {
        return @preg_match($pattern, $haystack) === 1;
    }
}
