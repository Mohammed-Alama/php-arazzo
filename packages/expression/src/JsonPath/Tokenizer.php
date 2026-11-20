<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\JsonPath;

/**
 * Tokenizes an RFC 9535 JSONPath expression.
 *
 * @internal stays out of the advertised contract; consumed by Parser.
 */
final class Tokenizer
{
    public function tokenize(string $expression): array
    {
        /** @var list<array{0: string, 1: string|float|null}> $tokens */
        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $ch = $expression[$i];

            if ($this->isWhitespace($ch)) {
                $i++;

                continue;
            }

            $matched = $this->matchSingleChar($ch, $tokens, $i, $length, $expression);
            if ($matched !== null) {
                $i = $matched;

                continue;
            }

            $matched = $this->matchString($expression, $i, $length, $tokens);
            if ($matched !== null) {
                $i = $matched;

                continue;
            }

            $matched = $this->matchNumber($expression, $i, $length, $tokens);
            if ($matched !== null) {
                $i = $matched;

                continue;
            }

            $matched = $this->matchWord($expression, $i, $length, $tokens);
            if ($matched !== null) {
                $i = $matched;

                continue;
            }

            $matched = $this->matchOperator($expression, $i, $length, $tokens);
            if ($matched !== null) {
                $i = $matched;

                continue;
            }

            // Unknown character: treat as single-char operator.
            $tokens[] = ['OP', $ch];
            $i++;
        }

        return $tokens;
    }

    private function isWhitespace(string $ch): bool
    {
        return $ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r";
    }

    private function matchSingleChar(string $ch, array &$tokens, int &$i, int $length, string $expression): ?int
    {
        $map = [
            '$' => ['ROOT', '$'],
            '@' => ['CURRENT', '@'],
            '[' => ['LBRACKET', '['],
            ']' => ['RBRACKET', ']'],
            ',' => ['COMMA', ','],
            ':' => ['COLON', ':'],
            '(' => ['LPAREN', '('],
            ')' => ['RPAREN', ')'],
        ];

        if (array_key_exists($ch, $map)) {
            $tokens[] = $map[$ch];
            $i++;

            return $i;
        }

        if ($ch === '.') {
            return $this->matchDot($expression, $i, $length, $tokens);
        }

        return null;
    }

    private function matchDot(string $expression, int &$i, int $length, array &$tokens): int
    {
        $i++;
        if ($i < $length && $expression[$i] === '.') {
            $i++;
            $tokens[] = ['RECURSIVE_DESCENT', '..'];
        } else {
            $tokens[] = ['DOT', '.'];
        }

        return $i;
    }

    private function matchString(string $expression, int &$i, int $length, array &$tokens): ?int
    {
        $ch = $expression[$i];
        if ($ch !== '"' && $ch !== "'") {
            return null;
        }

        $quote = $ch;
        $i++;
        $start = $i;
        while ($i < $length && $expression[$i] !== $quote) {
            if ($expression[$i] === '\\') {
                $i += 2;

                continue;
            }
            $i++;
        }
        $tokens[] = ['STRING', substr($expression, $start, $i - $start)];
        if ($i < $length) {
            $i++; // skip closing quote
        }

        return $i;
    }

    private function matchNumber(string $expression, int &$i, int $length, array &$tokens): ?int
    {
        $ch = $expression[$i];
        if ($ch !== '-' && !($ch >= '0' && $ch <= '9')) {
            return null;
        }

        $start = $i;
        if ($ch === '-') {
            $i++;
        }
        while ($i < $length && $expression[$i] >= '0' && $expression[$i] <= '9') {
            $i++;
        }
        $tokens[] = ['NUMBER', (float) substr($expression, $start, $i - $start)];

        return $i;
    }

    private function matchWord(string $expression, int &$i, int $length, array &$tokens): ?int
    {
        $ch = $expression[$i];
        if (!ctype_alpha($ch) && $ch !== '_') {
            return null;
        }

        $start = $i;
        while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
            $i++;
        }
        $tokens[] = ['WORD', substr($expression, $start, $i - $start)];

        return $i;
    }

    private function matchOperator(string $expression, int &$i, int $length, array &$tokens): ?int
    {
        if (!isset($expression[$i + 1])) {
            return null;
        }

        $two = $expression[$i].$expression[$i + 1];
        if (in_array($two, ['==', '!=', '<=', '>=', '&&', '||', '=~'], true)) {
            $tokens[] = ['OP', $two];
            $i += 2;

            return $i;
        }

        return null;
    }
}
