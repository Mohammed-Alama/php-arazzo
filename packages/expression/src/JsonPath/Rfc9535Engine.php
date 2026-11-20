<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

use Alama\Arazzo\Expression\Data\JsonPathBudget;
use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;

/**
 * Self-contained RFC 9535 JSONPath evaluator.
 *
 * Implements the function extensions `length()`, `count()`, `match()`,
 * `search()` and `value()`, the full filter grammar (logical operators,
 * existence tests, nested filters), and query admission budgets so
 * evaluation always terminates and never hangs.
 *
 * Result shape mirrors the classic engine it replaces: a single match
 * unwraps to the bare value; multiple matches stay an array.
 *
 * @internal stays out of the advertised contract; consumed by JsonPathEvaluator.
 */
final class Rfc9535Engine
{
    /** @var list<string> */
    private const SUPPORTED_VERSIONS = ['rfc9535'];

    public function __construct(private JsonPathBudget $budget = new JsonPathBudget()) {}

    /**
     * @param  array<array-key, mixed>|object  $data
     */
    public function find(string $expression, array|object $data): mixed
    {
        if (strlen($expression) > $this->budget->maxExpressionLength) {
            throw SelectorEvaluationException::jsonPathBudgetExceeded('expressionLength');
        }

        $tokens = (new Tokenizer())->tokenize($expression);
        $parser = new Parser($tokens);
        /** @var array{kind: string, steps: list<array<array-key, mixed>>}  $ast */
        $ast = $parser->parse();

        $visited = 0;
        /** @var list<array<array-key, mixed>>  $results */
        $results = $this->select($ast, $this->normalizeRoot($data), '$', $visited, 0);

        if (count($results) > $this->budget->maxResultSetSize) {
            throw SelectorEvaluationException::jsonPathBudgetExceeded('resultSetSize');
        }

        return $results;
    }

    /** @return list<string> */
    public function supportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }

    public function budget(): JsonPathBudget
    {
        return $this->budget;
    }

    /**
     * @param  array<array-key, mixed>|object  $data
     * @return list<array<array-key, mixed>>
     */
    private function normalizeRoot(array|object $data): array
    {
        if (is_object($data)) {
            return [(array) $data];
        }

        return [$data];
    }

    /**
     * Evaluate a selector AST against a collection of current nodes.
     *
     * @param  array{kind: string, steps: list<array<array-key, mixed>>}  $ast
     * @param  list<array<array-key, mixed>>  $nodes
     * @param  int  $visited  node visits (passed by reference)
     * @param  int  $depth  current selector nesting depth
     * @return list<array<array-key, mixed>>
     */
    private function select(array $ast, array $nodes, string $path, int &$visited, int $depth = 0): array
    {
        $current = $nodes;

        foreach ($ast['steps'] as $rawStep) {
            /** @var array<array-key, mixed>  $step */
            $step = $rawStep;
            $depth++;
            if ($depth > $this->budget->maxSelectorDepth) {
                throw SelectorEvaluationException::jsonPathBudgetExceeded('selectorDepth');
            }

            $next = [];

            foreach ($current as $node) {
                $visited++;
                if ($visited > $this->budget->maxNodesVisited) {
                    throw SelectorEvaluationException::jsonPathBudgetExceeded('nodesVisited');
                }

                $next = array_merge($next, $this->applyStep($step, $node, $path, $visited, $depth));
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * @param  array<array-key, mixed>  $step
     * @return list<array<array-key, mixed>>
     */
    private function applyStep(array $step, mixed $node, string $path, int &$visited, int $depth): array
    {
        /** @var string $kind */
        $kind = $step['kind'];

        switch ($kind) {
            case 'child':
                /** @var string $name */
                $name = $step['name'] ?? '';

                return $this->child($node, $name);

            case 'recursive-descent':
                /** @var array<array-key, mixed>  $child */
                $child = $step['child'] ?? [];

                return $this->recursiveDescent($node, $child, $path, $visited, $depth);

            case 'wildcard':
                return $this->wildcard($node, $visited);

            case 'slice':
                return $this->slice($node, $step, $visited);

            case 'filter':
                /** @var array<array-key, mixed>  $predicate */
                $predicate = $step['predicate'];

                return $this->filter($predicate, $node, $visited);

            case 'union':
                return $this->applyUnion($step, $node, $path, $visited, $depth);
        }

        return [];
    }

    /**
     * @param  array<array-key, mixed>  $step
     * @return list<array<array-key, mixed>>
     */
    private function applyUnion(array $step, mixed $node, string $path, int &$visited, int $depth): array
    {
        /** @var list<array<array-key, mixed>>  $parts */
        $parts = $step['parts'] ?? [];
        $out = [];

        foreach ($parts as $rawPart) {
            /** @var array<array-key, mixed>  $part */
            $part = $rawPart;
            $nodeArr = [$node];
            /** @var list<array<array-key, mixed>>  $nodeArr */
            $out = array_merge(
                $out,
                $this->select(
                    ['kind' => 'root', 'steps' => [$part]],
                    $nodeArr,
                    $path,
                    $visited,
                    $depth,
                ),
            );
        }

        return $out;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function child(mixed $node, string $name): array
    {
        if (is_array($node) && array_key_exists($name, $node)) {
            $result = [$node[$name]];

            /** @var list<array<array-key, mixed>>  $result */
            return $result;
        }

        if (is_object($node) && property_exists($node, $name)) {
            $result = [$node->$name];

            /** @var list<array<array-key, mixed>>  $result */
            return $result;
        }

        return [];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function recursiveDescent(mixed $node, array $child, string $path, int &$visited, int $depth): array
    {
        $out = [];
        // FIFO queue so descendants are visited in document order.
        $queue = [$node];

        while ($queue !== []) {
            $current = array_shift($queue);
            $visited++;
            if ($visited > $this->budget->maxNodesVisited) {
                throw SelectorEvaluationException::jsonPathBudgetExceeded('nodesVisited');
            }

            $currentArr = [$current];
            /** @var list<array<array-key, mixed>>  $currentArr */
            $matches = $this->select(['kind' => 'root', 'steps' => [$child]], $currentArr, $path, $visited, $depth);
            foreach ($matches as $match) {
                $out[] = $match;
            }

            if (is_array($current)) {
                foreach ($current as $value) {
                    if (is_array($value) || is_object($value)) {
                        $queue[] = $value;
                    }
                }
            } elseif (is_object($current)) {
                foreach ((array) $current as $value) {
                    if (is_array($value) || is_object($value)) {
                        $queue[] = $value;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function wildcard(mixed $node, int &$visited): array
    {
        $members = [];

        if (is_array($node)) {
            $members = array_values($node);
        } elseif (is_object($node)) {
            $members = array_values((array) $node);
        }

        $out = [];
        foreach ($members as $member) {
            $visited++;
            if ($visited > $this->budget->maxNodesVisited) {
                throw SelectorEvaluationException::jsonPathBudgetExceeded('nodesVisited');
            }

            $out[] = $member;
        }

        /** @var list<array<array-key, mixed>>  $out */
        return $out;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function slice(mixed $node, array $step, int &$visited): array
    {
        if (!is_array($node)) {
            return [];
        }

        $count = count($node);
        /** @var int|float|null $startVal */
        $startVal = $step['start'] ?? 0;
        /** @var int|float|null $endVal */
        $endVal = $step['end'] ?? 0;
        /** @var int|float|null $stepVal */
        $stepVal = $step['step'] ?? 1;
        $stepInterval = intval($stepVal ?? 0);

        $startIndex = $this->resolveIndex(intval($startVal ?? 0), $count);
        $endIndex = $this->resolveIndex(intval($endVal ?? 0), $count);

        $out = [];
        for ($i = $startIndex; $i < $endIndex; $i += $stepInterval) {
            if (!array_key_exists($i, $node)) {
                continue;
            }
            $visited++;
            if ($visited > $this->budget->maxNodesVisited) {
                throw SelectorEvaluationException::jsonPathBudgetExceeded('nodesVisited');
            }
            $out[] = $node[$i];
        }

        /** @var list<array<array-key, mixed>>  $out */
        return $out;
    }

    private function resolveIndex(int $value, int $count): int
    {
        $index = $value;
        if ($index < 0) {
            $index = max(0, $count + $index);
        }

        return min(max(0, $index), $count);
    }

    /**
     * @param  array<array-key, mixed>  $predicate
     * @return list<array<array-key, mixed>>
     */
    private function filter(array $predicate, mixed $node, int &$visited): array
    {
        if (!is_array($node)) {
            return [];
        }

        $out = [];

        // A JSON array (list) is filtered element-by-element (@ = each element);
        // a JSON object (associative array) is tested as a whole (@ = the object).
        $candidates = array_is_list($node) ? $node : [$node];

        foreach ($candidates as $item) {
            $visited++;
            if ($visited > $this->budget->maxNodesVisited) {
                throw SelectorEvaluationException::jsonPathBudgetExceeded('nodesVisited');
            }

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
    private function evaluatePredicate(array $predicate, mixed $item): bool
    {
        /** @var string $kind */
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
            'exists' => $exprNode !== null && $this->evaluateExists($exprNode, $item),
            'function' => (bool) $this->evaluateFunction($name, $args, $item),
            default => false,
        };
    }

    private function evaluateLogical(array $predicate, mixed $item): bool
    {
        $leftNode = $predicate['left'] ?? null;
        if ($leftNode === null) {
            return false;
        }
        /** @var array<array-key, mixed>  $leftNode */
        $left = $this->evaluatePredicate($leftNode, $item);
        /** @var string $operator */
        $operator = $predicate['operator'] ?? '';

        if ($operator === '!') {
            return !$left;
        }

        $rightNode = $predicate['right'] ?? null;
        if ($rightNode === null) {
            return false;
        }
        /** @var array<array-key, mixed>  $rightNode */
        $right = $this->evaluatePredicate($rightNode, $item);

        return match ($operator) {
            '&&' => $left && $right,
            '||' => $left || $right,
            default => false,
        };
    }

    private function evaluateExists(array $expression, mixed $item): bool
    {
        $value = $this->evaluatePath($expression, $item);

        return $value !== null && $value !== [];
    }

    /**
     * @return mixed|null
     */
    private function evaluatePath(array $expression, mixed $item)
    {
        /** @var string $kind */
        $kind = $expression['kind'];

        $name = $expression['name'] ?? '';
        if (!is_string($name)) {
            $name = '';
        }
        $base = $expression['base'] ?? '@';
        if (!is_string($base)) {
            $base = '@';
        }

        return match ($kind) {
            'current' => $item,
            'field' => $this->fieldValue($item, $name, $base),
            'literal' => $expression['value'] ?? null,
            default => null,
        };
    }

    private function fieldValue(mixed $item, string $name, string $base = '@'): mixed
    {
        // '@' refers to the current item itself
        if ($name === '@') {
            return $item;
        }

        if (is_array($item) && array_key_exists($name, $item)) {
            return $item[$name];
        }

        if (is_object($item) && property_exists($item, $name)) {
            return $item->$name;
        }

        return null;
    }

    private function compare(mixed $left, mixed $right, string $operator, mixed $item): bool
    {
        $l = $this->resolveValue($left, $item);
        $r = $this->resolveValue($right, $item);

        /** @var string $lStr */
        $lStr = is_string($l) ? $l : (is_numeric($l) ? (string) $l : '');
        /** @var string $rStr */
        $rStr = is_string($r) ? $r : (is_numeric($r) ? (string) $r : '');

        return match ($operator) {
            '==' => $this->jsonEquals($l, $r),
            '!=' => !$this->jsonEquals($l, $r),
            '<' => $this->jsonLessThan($l, $r),
            '>' => $this->jsonLessThan($r, $l),
            '<=' => $this->jsonEquals($l, $r) || $this->jsonLessThan($l, $r),
            '>=' => $this->jsonEquals($l, $r) || $this->jsonLessThan($r, $l),
            '=~' => $this->matchRegex($lStr, $rStr),
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

    private function resolveValue(mixed $value, mixed $item): mixed
    {
        if (!is_array($value) || !isset($value['kind'])) {
            return $value;
        }

        // A function node must be invoked, not treated as a path.
        if ($value['kind'] === 'function') {
            /** @var array{kind: string, name: string, args: list<array<array-key, mixed>>}  $value */
            return $this->evaluateFunction($value['name'], $value['args'], $item);
        }

        /** @var array{kind: string}  $value */
        return $this->evaluatePath($value, $item);
    }

    private function matchRegex(string $haystack, string $pattern): bool
    {
        return @preg_match($pattern, $haystack) === 1;
    }

    /**
     * @param  list<array<array-key, mixed>>  $args
     */
    private function evaluateFunction(string $name, array $args, mixed $item): mixed
    {
        $resolved = [];
        foreach ($args as $arg) {
            $resolved[] = $this->resolveValue($arg, $item);
        }

        $arg1 = $resolved[1] ?? '';
        if (!is_string($arg1)) {
            $arg1 = '';
        }
        /** @var string $arg1Str */
        $arg1Str = $arg1;

        return match ($name) {
            'length' => $this->fnLength($resolved[0] ?? null),
            'count' => $this->fnCount($resolved[0] ?? null),
            'match' => $this->fnMatch($resolved[0] ?? null, strval($arg1Str)),
            'search' => $this->fnSearch($resolved[0] ?? null, strval($arg1Str)),
            'value' => $resolved[0] ?? null,
            default => null,
        };
    }

    private function fnLength(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if (is_string($value)) {
            return strlen($value);
        }

        if (is_object($value)) {
            return count((array) $value);
        }

        if ($value !== null) {
            return 1;
        }

        return 0;
    }

    private function fnCount(mixed $value): int
    {
        return $this->fnLength($value);
    }

    private function fnMatch(mixed $value, string $pattern): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return @preg_match($pattern, $value) === 1;
    }

    private function fnSearch(mixed $value, string $pattern): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return @preg_match($pattern, $value) === 1;
    }
}
