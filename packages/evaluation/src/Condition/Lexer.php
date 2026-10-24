<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

use Alama\Arazzo\Evaluation\Enum\TokenKind;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class Lexer
{
    /** @return list<Token> */
    public function lex(string $condition): array
    {
        $tokens = [];
        $length = strlen($condition);
        $i = 0;

        while ($i < $length) {
            $char = $condition[$i];

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $i++;

                continue;
            }

            $two = substr($condition, $i, 2);
            if (isset(TokenKind::OPERATORS[$two])) {
                $tokens[] = new Token(TokenKind::OPERATORS[$two], $two, $i);
                $i += 2;

                continue;
            }

            if (isset(TokenKind::OPERATORS[$char])) {
                $tokens[] = new Token(TokenKind::OPERATORS[$char], $char, $i);
                $i++;

                continue;
            }

            if ($char === '\'' || $char === '"') {
                [$token, $i] = $this->scanString($condition, $i);
                $tokens[] = $token;

                continue;
            }

            if (preg_match('/-?\d+(?:\.\d+)?/', $condition, $m, PREG_OFFSET_CAPTURE, $i) === 1 && $m[0][1] === $i) {
                $tokens[] = new Token(TokenKind::Number, $m[0][0], $i);
                $i += strlen($m[0][0]);

                continue;
            }

            if ($char === '$' || $char === '{') {
                if ($char === '{') {
                    $closing = strpos($condition, '}', $i);
                    if ($closing === false) {
                        throw new ConditionSyntaxException("Unterminated '{' runtime expression in condition at offset {$i}.");
                    }

                    $raw = trim(substr($condition, $i + 1, $closing - $i - 1));
                    $end = $closing + 1;
                } else {
                    $end = $this->expressionEnd($condition, $i);
                    $raw = rtrim(substr($condition, $i, $end - $i));
                }

                if ($raw === '' || $raw[0] !== '$') {
                    throw new ConditionSyntaxException("Malformed runtime expression in condition at offset {$i}.");
                }

                $tokens[] = new Token(TokenKind::Expr, $raw, $i);
                $i = $end;

                continue;
            }

            if (preg_match('/[A-Za-z_][A-Za-z0-9_.\-\/]*/', $condition, $m, PREG_OFFSET_CAPTURE, $i) === 1 && $m[0][1] === $i) {
                $tokens[] = new Token(TokenKind::Ident, $m[0][0], $i);
                $i += strlen($m[0][0]);

                continue;
            }

            throw new ConditionSyntaxException("Unexpected character '{$char}' in condition at offset {$i}.");
        }

        return $tokens;
    }

    /**
     * Scan a quoted string literal starting at `$start`, returning the token and
     * the index just past its closing quote.
     *
     * The spec escapes a literal single quote inside a single-quoted string by
     * doubling it (`''`), so the closing quote is the first one that is not part
     * of such a pair. Double quotes are a lenient extension and simply close at
     * the next quote.
     *
     * @return array{Token, int}
     */
    private function scanString(string $condition, int $start): array
    {
        $length = strlen($condition);
        $quote = $condition[$start];
        $value = '';
        $i = $start + 1;

        while (true) {
            if ($i >= $length) {
                throw new ConditionSyntaxException("Unterminated string literal in condition at offset {$start}.");
            }

            if ($condition[$i] === $quote) {
                if ($quote === '\'' && ($condition[$i + 1] ?? '') === '\'') {
                    $value .= '\'';
                    $i += 2;

                    continue;
                }

                return [new Token(TokenKind::String, $value, $start), $i + 1];
            }

            $value .= $condition[$i];
            $i++;
        }
    }

    /**
     * Runtime expressions run until whitespace or a top-level delimiter.
     */
    private function expressionEnd(string $condition, int $start): int
    {
        $length = strlen($condition);
        $i = $start;

        while ($i < $length) {
            $char = $condition[$i];
            if ($char === ' ' || $char === "\t" || $char === ')') {
                return $i;
            }

            $two = substr($condition, $i, 2);
            if ($two === '&&' || $two === '||') {
                return $i;
            }

            if ($i > $start && ($char === '=' || $char === '!' || $char === '<' || $char === '>')) {
                return $i;
            }

            $i++;
        }

        return $length;
    }
}
