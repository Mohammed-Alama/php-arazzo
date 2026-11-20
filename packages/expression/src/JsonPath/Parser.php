<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

/**
 * Recursive-descent parser for RFC 9535 JSONPath expressions.
 *
 * @internal stays out of the advertised contract; consumed by Rfc9535Engine.
 */
final class Parser
{
    /** @var list<array{string, string|null}> */
    private array $tokens;

    private int $pos = 0;

    private int $tokenCount;

    public function __construct(array $tokens)
    {
        /** @var list<array{string, string|null}> $tokens */
        $this->tokens = $tokens;
        $this->tokenCount = count($tokens);
    }

    /**
     * @return array{kind: string, steps: list<array<array-key, mixed>>}
     */
    public function parse(): array
    {
        $this->expect('ROOT', '$');

        $steps = [];

        while ($this->pos < $this->tokenCount) {
            $token = $this->currentToken();

            if ($token[0] === 'DOT') {
                $this->pos++;
                $steps[] = $this->parseSelectorTail();
            } elseif ($token[0] === 'LBRACKET') {
                $steps[] = $this->parseBracket();
            } elseif ($token[0] === 'RECURSIVE_DESCENT') {
                $this->pos++;
                $steps[] = $this->parseRecursiveDescentTail();
            } else {
                break;
            }
        }

        if ($this->pos < $this->tokenCount) {
            throw new \RuntimeException('Unexpected trailing tokens in JSONPath expression');
        }

        return ['kind' => 'selector', 'steps' => $steps];
    }

    /**
     * @return array{kind: string, name?: string, child?: array<array-key, mixed>|null}
     */
    private function parseSelectorTail(): array
    {
        $token = $this->currentToken();
        $value = $token[1] ?? '';

        if ($token[0] === 'WORD') {
            $this->pos++;

            return ['kind' => 'child', 'name' => $value];
        }

        if ($value === '*') {
            $this->pos++;

            return ['kind' => 'wildcard', 'child' => null];
        }

        throw new \RuntimeException('Expected member name after dot');
    }

    /**
     * @return array{kind: string}
     */
    private function parseRecursiveDescentTail(): array
    {
        $token = $this->currentToken();
        $value = $token[1];

        if ($value === '*') {
            $this->pos++;

            return ['kind' => 'recursive-descent', 'child' => ['kind' => 'wildcard', 'child' => null]];
        }

        if ($token[0] === 'WORD') {
            $this->pos++;

            return ['kind' => 'recursive-descent', 'child' => ['kind' => 'child', 'name' => $value]];
        }

        if ($token[0] === 'LBRACKET') {
            $this->pos++;
            $inner = $this->parseBracketContent();
            $this->expect('RBRACKET', ']');

            return ['kind' => 'recursive-descent', 'child' => $inner];
        }

        throw new \RuntimeException('Expected member name after ..');
    }

    /**
     * @return array{kind: string}
     */
    private function parseBracket(): array
    {
        $this->pos++; // consume '['

        $token = $this->currentToken();
        $value = $token[1];

        // Filter: [?expr]
        if ($value === '?') {
            $this->pos++; // consume '?'
            $predicate = $this->parseFilterExpression();
            $this->expect('RBRACKET', ']');

            return ['kind' => 'filter', 'predicate' => $predicate];
        }

        $content = $this->parseBracketContent();
        $this->expect('RBRACKET', ']');

        return $content;
    }

    /**
     * @return array{kind: string, start?: int, end?: int, step?: int, parts?: list<array<array-key, mixed>>, name?: string, child?: array<array-key, mixed>|null}
     */
    private function parseBracketContent(): array
    {
        $first = $this->parseSelectorPart();

        if ($this->pos < $this->tokenCount && $this->tokens[$this->pos][0] === 'COMMA') {
            return $this->parseUnion($first);
        }

        return $first;
    }

    /**
     * @return array{kind: string, start?: int, end?: int, step?: int, name?: string, child?: array<array-key, mixed>|null}
     */
    private function parseSelectorPart(): array
    {
        $token = $this->currentToken();
        $type = $token[0];
        $value = $token[1];

        if ($value === '*') {
            $this->pos++;

            return ['kind' => 'wildcard', 'child' => null];
        }

        if ($type === 'STRING') {
            $this->pos++;

            return ['kind' => 'child', 'name' => $value ?? ''];
        }

        if ($type === 'COLON') {
            return $this->parseSlice(null);
        }

        if (is_numeric($value)) {
            $this->pos++;
            $start = (int) $value;

            if ($this->pos < $this->tokenCount && $this->tokens[$this->pos][0] === 'COLON') {
                return $this->parseSlice($start);
            }

            return ['kind' => 'slice', 'start' => $start, 'end' => $start + 1, 'step' => 1];
        }

        throw new \RuntimeException('Unsupported bracket selector');
    }

    /**
     * @return array{kind: string, start: int, end: int, step: int}
     */
    private function parseSlice(?int $start): array
    {
        $this->pos++; // consume ':'

        $end = PHP_INT_MAX;
        $step = 1;

        $token = $this->currentToken();
        $value = $token[1];

        if ($token[0] !== 'COLON' && is_numeric($value)) {
            $this->pos++;
            $end = (int) $value;
        }

        if ($this->pos < $this->tokenCount && $this->tokens[$this->pos][0] === 'COLON') {
            $this->pos++; // consume second ':'
            $token = $this->currentToken();
            $value = $token[1];
            if (is_numeric($value)) {
                $this->pos++;
                $step = (int) $value;
            }
        }

        return ['kind' => 'slice', 'start' => $start ?? 0, 'end' => $end, 'step' => $step];
    }

    /**
     * @param  array{kind: string, start?: int, end?: int, step?: int, name?: string, child?: array<array-key, mixed>|null}  $first
     * @return array{kind: string, parts: list<array<array-key, mixed>>}
     */
    private function parseUnion(array $first): array
    {
        $parts = [$first];

        while ($this->pos < $this->tokenCount && $this->tokens[$this->pos][0] === 'COMMA') {
            $this->pos++; // consume ','
            $parts[] = $this->parseSelectorPart();
        }

        return ['kind' => 'union', 'parts' => $parts];
    }

    /**
     * @return array{kind: string}
     */
    private function parseFilterExpression(): array
    {
        return $this->parseOr();
    }

    /**
     * @return array{kind: string}
     */
    private function parseOr(): array
    {
        $left = $this->parseAnd();

        while ($this->pos < $this->tokenCount && $this->tokens[$this->pos][1] === '||') {
            $this->pos++;
            $right = $this->parseAnd();
            $left = ['kind' => 'logical', 'operator' => '||', 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * @return array{kind: string}
     */
    private function parseAnd(): array
    {
        $left = $this->parseNot();

        while ($this->pos < $this->tokenCount && $this->tokens[$this->pos][1] === '&&') {
            $this->pos++;
            $right = $this->parseAnd();
            $left = ['kind' => 'logical', 'operator' => '&&', 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * @return array{kind: string}
     */
    private function parseNot(): array
    {
        if ($this->pos < $this->tokenCount && $this->tokens[$this->pos][1] === '!') {
            $this->pos++;
            $operand = $this->parseNot();

            return ['kind' => 'logical', 'operator' => '!', 'left' => $operand, 'right' => null];
        }

        return $this->parseComparison();
    }

    /**
     * @return array{kind: string}
     */
    private function parseComparison(): array
    {
        $left = $this->parsePrimary();

        $token = $this->currentToken();
        $value = $token[1];

        if (in_array($value, ['==', '!=', '<', '>', '<=', '>=', '=~'], true)) {
            $this->pos++;
            $right = $this->parsePrimary();

            return ['kind' => 'comparison', 'operator' => $value, 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * @return array{kind: string}
     */
    private function parsePrimary(): array
    {
        $token = $this->currentToken();
        $type = $token[0];
        $value = $token[1];

        if ($type === 'WORD' && $value === 'exists') {
            $this->pos++;
            $this->expect('LPAREN', '(');
            $expr = $this->parsePathExpression();
            $this->expect('RPAREN', ')');

            return ['kind' => 'exists', 'expression' => $expr];
        }

        if ($type === 'WORD' && in_array($value, ['length', 'count', 'match', 'search', 'value'], true)) {
            return $this->parseFunction($value);
        }

        if (($type === 'WORD' || $type === 'CURRENT') && $value === '@') {
            $this->pos++;
            $next = $this->currentToken();
            if ($next[0] === 'DOT') {
                $this->pos++; // consume '.'
                $nameToken = $this->currentToken();
                if ($nameToken[0] !== 'WORD') {
                    throw new \RuntimeException('Expected field name after @.');
                }
                $this->pos++;

                return ['kind' => 'field', 'name' => $nameToken[1], 'base' => '@'];
            }

            return ['kind' => 'current'];
        }

        if ($type === 'STRING') {
            $this->pos++;

            return ['kind' => 'literal', 'value' => $value];
        }

        if (is_numeric($value)) {
            $this->pos++;

            return ['kind' => 'literal', 'value' => (float) $value];
        }

        // Literals: null, true, false.
        if ($type === 'WORD' && in_array($value, ['null', 'true', 'false'], true)) {
            $this->pos++;

            return ['kind' => 'literal', 'value' => $value === 'null' ? null : ($value === 'true')];
        }

        if ($type === 'DOT') {
            return $this->parsePathExpression();
        }

        // Grouping: (expr)
        if ($type === 'LPAREN') {
            $this->pos++; // consume '('
            $expr = $this->parseFilterExpression();
            $this->expect('RPAREN', ')');

            return $expr;
        }

        throw new \RuntimeException('Unexpected token in filter expression: '.var_export($value, true));
    }

    /**
     * @return array{kind: string, name: string}
     */
    private function parsePathExpression(): array
    {
        // @.field or $.field or just .field
        $token = $this->currentToken();
        $type = $token[0];
        $value = $token[1];

        if (($type === 'WORD' || $type === 'CURRENT') && ($value === '@' || $value === '$')) {
            $this->pos++;
            $this->expect('DOT', '.');
        } elseif ($type === 'DOT') {
            $this->pos++;
        }

        $token = $this->currentToken();
        $name = $token[1].'';

        if ($token[0] !== 'WORD') {
            throw new \RuntimeException('Expected field name in path expression');
        }

        $this->pos++;

        return ['kind' => 'field', 'name' => $name];
    }

    /**
     * @return array{kind: string, name: string, args: list<array<array-key, mixed>>}
     */
    private function parseFunction(string $name): array
    {
        $this->pos++; // consume function name
        $this->expect('LPAREN', '(');

        $args = [];

        if (!($this->pos < $this->tokenCount && $this->tokens[$this->pos][0] === 'RPAREN')) {
            $args[] = $this->parseFilterExpression();

            while ($this->pos < $this->tokenCount && $this->tokens[$this->pos][0] === 'COMMA') {
                $this->pos++; // consume ','
                $args[] = $this->parseFilterExpression();
            }
        }

        $this->expect('RPAREN', ')');

        return ['kind' => 'function', 'name' => $name, 'args' => $args];
    }

    /**
     * @return array{string, string|null}
     */
    private function currentToken(): array
    {
        if ($this->pos >= $this->tokenCount) {
            throw new \RuntimeException('Unexpected end of JSONPath expression');
        }

        return $this->tokens[$this->pos];
    }

    private function expect(string $type, ?string $value = null): void
    {
        $token = $this->currentToken();
        $actualType = $token[0];
        $actualValue = $token[1];

        if ($actualType !== $type) {
            throw new \RuntimeException("Expected token {$type}, got ".var_export($actualType, true));
        }

        if ($value !== null && $actualValue !== $value) {
            throw new \RuntimeException("Expected token {$type} '{$value}', got ".var_export($actualValue, true));
        }

        $this->pos++;
    }
}
