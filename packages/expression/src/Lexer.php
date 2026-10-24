<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Data\Token;
use Alama\Arazzo\Expression\Enum\TokenKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;

/**
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
final class Lexer
{
    private const array KEYWORDS = [
        'inputs', 'outputs', 'steps', 'workflows', 'sourceDescriptions',
        'components', 'response', 'request', 'url', 'method', 'statusCode',
        'body', 'header', 'query', 'path', 'message', 'payload', 'self',
    ];

    /** One character of a name or pointer segment. */
    private const string WORD_CHARACTER = '/[A-Za-z0-9_\-~%@+:]/';

    /** @return list<Token> */
    public function tokenize(string $raw): array
    {
        [$inner, $base] = $this->unwrap($raw);

        if ($inner === '') {
            throw new ExpressionSyntaxException("Empty expression: {$raw}", $raw, -1, '', 'expr.syntax');
        }

        $tokens = [];
        $length = strlen($inner);
        $offset = 0;
        $inPointer = false;

        while ($offset < $length) {
            $character = $inner[$offset];
            $absoluteOffset = $offset + $base;

            if ($character === '#') {
                $inPointer = true;
            }

            $kind = $this->singleCharacterKind($character);
            if ($kind !== null) {
                $tokens[] = new Token($kind, $character, $absoluteOffset);
                $offset++;

                continue;
            }

            if (preg_match(self::WORD_CHARACTER, $character) === 1) {
                [$word, $offset] = $this->scanWord($inner, $offset, $length);
                $tokens[] = new Token($this->wordKind($word, $inPointer), $word, $absoluteOffset);

                continue;
            }

            throw new ExpressionSyntaxException(
                "Illegal character '{$character}' at offset {$absoluteOffset} in expression: {$raw}",
                $raw, $absoluteOffset, '', 'expr.syntax',
            );
        }

        return $tokens;
    }

    /**
     * Strip the expression wrapper, returning the token stream's source and the
     * byte offset its index zero maps to in the original expression.
     *
     * @return array{string, int}
     */
    private function unwrap(string $raw): array
    {
        if (str_starts_with($raw, '{$') && str_ends_with($raw, '}')) {
            return [substr($raw, 2, -1), 2];
        }

        if (str_starts_with($raw, '${') && str_ends_with($raw, '}')) {
            // `${token}` is the alternate Arazzo spelling of `{$token}`:
            // drop the wrapper braces but keep the leading `$`.
            return ['$'.substr($raw, 2, -1), 0];
        }

        if (str_starts_with($raw, '$')) {
            return [substr($raw, 1), 1];
        }

        throw new ExpressionSyntaxException(
            "Expression must start with $ or be wrapped in {\$...}: {$raw}",
            $raw, 0, '', 'expr.syntax',
        );
    }

    private function singleCharacterKind(string $character): ?TokenKind
    {
        return match ($character) {
            '$' => TokenKind::Dollar,
            '.' => TokenKind::Dot,
            '#' => TokenKind::Hash,
            '/' => TokenKind::Slash,
            default => null,
        };
    }

    /** @return array{string, int} */
    private function scanWord(string $inner, int $start, int $length): array
    {
        $offset = $start;
        while ($offset < $length && preg_match(self::WORD_CHARACTER, $inner[$offset]) === 1) {
            $offset++;
        }

        return [substr($inner, $start, $offset - $start), $offset];
    }

    private function wordKind(string $word, bool $inPointer): TokenKind
    {
        if ($inPointer) {
            return TokenKind::PointerSegment;
        }

        return in_array($word, self::KEYWORDS, true) ? TokenKind::Keyword : TokenKind::Name;
    }
}
