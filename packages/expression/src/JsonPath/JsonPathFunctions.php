<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

/**
 * JSONPath function extensions (`length`, `count`, `match`, `search`, `value`)
 * and path-expression resolution (`current`, `field`, `literal`, `exists`).
 *
 * @internal stays out of the advertised contract; consumed by PredicateEvaluator.
 */
final class JsonPathFunctions
{
    /**
     * @param  list<array<array-key, mixed>>  $args
     */
    public function evaluateFunction(string $name, array $args, mixed $item): mixed
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
            'match' => $this->fnMatch($resolved[0] ?? null, $arg1Str),
            'search' => $this->fnSearch($resolved[0] ?? null, $arg1Str),
            'value' => $resolved[0] ?? null,
            default => null,
        };
    }

    /**
     * @return mixed|null
     */
    public function resolveValue(mixed $value, mixed $item): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!isset($value['kind'])) {
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

    /**
     * @param  array<array-key, mixed>  $expression
     * @return mixed|null
     */
    public function evaluatePath(array $expression, mixed $item)
    {
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

    public function evaluateExists(array $expression, mixed $item): bool
    {
        $value = $this->evaluatePath($expression, $item);

        return $value !== null && $value !== [];
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
        return $this->fnRegex($value, $pattern);
    }

    private function fnSearch(mixed $value, string $pattern): bool
    {
        return $this->fnRegex($value, $pattern);
    }

    private function fnRegex(mixed $value, string $pattern): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return @preg_match($pattern, $value) === 1;
    }
}
