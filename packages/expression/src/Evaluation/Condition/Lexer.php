<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Condition;

use Alama\Arazzo\Expression\Evaluation\Enum\TokenKind;

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
                $end = strpos($condition, $char, $i + 1);
                if ($end === false) {
                    throw new ConditionSyntaxException("Unterminated string literal in condition at offset {$i}.");
                }

                $tokens[] = new Token(TokenKind::String, substr($condition, $i + 1, $end - $i - 1), $i);
                $i = $end + 1;

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
