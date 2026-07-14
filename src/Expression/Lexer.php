<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Expression;

final class Lexer
{
    private const KEYWORDS = [
        'inputs', 'outputs', 'steps', 'workflows', 'sourceDescriptions',
        'components', 'response', 'request', 'url', 'method', 'statusCode',
        'body', 'header',
    ];

    /** @return list<Token> */
    public function tokenize(string $raw): array
    {
        if (!str_starts_with($raw, '{$') || !str_ends_with($raw, '}')) {
            throw new ExpressionSyntaxException(
                "Expression must be wrapped in {\$...}: {$raw}",
                '', 'expr.syntax',
            );
        }
        $inner = substr($raw, 2, -1);
        if ($inner === '') {
            throw new ExpressionSyntaxException("Empty expression: {$raw}", '', 'expr.syntax');
        }

        $tokens = [];
        $len = strlen($inner);
        $i = 0;
        $inPointer = false;

        while ($i < $len) {
            $ch = $inner[$i];

            $absOffset = $i + 2;

            if ($ch === '$') {
                $tokens[] = new Token(TokenKind::Dollar, '$', $absOffset);
                $i++;

                continue;
            }
            if ($ch === '.') {
                $tokens[] = new Token(TokenKind::Dot, '.', $absOffset);
                $i++;

                continue;
            }
            if ($ch === '#') {
                $tokens[] = new Token(TokenKind::Hash, '#', $absOffset);
                $i++;
                $inPointer = true;

                continue;
            }
            if ($ch === '/') {
                $tokens[] = new Token(TokenKind::Slash, '/', $absOffset);
                $i++;

                continue;
            }

            if (preg_match('/[A-Za-z0-9_\-~%@+:]/', $ch) === 1) {
                $start = $i;
                while ($i < $len && preg_match('/[A-Za-z0-9_\-~%@+:]/', $inner[$i]) === 1) {
                    $i++;
                }
                $word = substr($inner, $start, $i - $start);
                if ($inPointer) {
                    $tokens[] = new Token(TokenKind::PointerSegment, $word, $absOffset);
                } elseif (in_array($word, self::KEYWORDS, true)) {
                    $tokens[] = new Token(TokenKind::Keyword, $word, $absOffset);
                } else {
                    $tokens[] = new Token(TokenKind::Name, $word, $absOffset);
                }

                continue;
            }

            throw new ExpressionSyntaxException(
                "Illegal character '{$ch}' at offset {$absOffset} in expression: {$raw}",
                '', 'expr.syntax',
            );
        }

        return $tokens;
    }
}
