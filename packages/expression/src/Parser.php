<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\InputPart;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Ast\MessageRef;
use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\OutputRef;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\SelfRef;
use Alama\Arazzo\Expression\Ast\SourceRef;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Ast\WorkflowRef;
use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Data\Token;
use Alama\Arazzo\Expression\Enum\TokenKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Interfaces\ParserInterface;

/**
 * Recursive-descent parser for the runtime-expression grammar.
 *
 * PHPMD's ExcessiveClassComplexity and CouplingBetweenObjects findings stay
 * above the default thresholds here, deliberately: the class is one pass over
 * one grammar, so its weight is the grammar's branch count and its coupling is
 * the AST vocabulary every production constructs. Splitting productions across
 * objects would share the token cursor and lookahead without removing a single
 * decision; the grammar reads better in one place. The per-method findings are
 * fixed, not waived (#260).
 *
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
final class Parser implements ParserInterface
{
    public function __construct(
        private readonly Lexer $lexer = new Lexer(),
    ) {}

    public function parse(string $raw): ExpressionAst
    {
        $tokens = $this->lexer->tokenize($raw);
        if ($tokens === []) {
            $this->fail("Empty expression: {$raw}", $raw);
        }

        $i = 0;
        // Optionally consume Dollar ($) token if it's there
        if ($tokens[$i]->kind === TokenKind::Dollar) {
            $i++;
            if (!isset($tokens[$i])) {
                $this->fail("Expression ended after \$: {$raw}", $raw);
            }
        }

        $head = $tokens[$i];
        if ($head->kind !== TokenKind::Keyword) {
            $this->fail("Expression must start with a keyword: {$raw}", $raw, $head);
        }

        return match ($head->value) {
            'inputs' => $this->parseSimpleRef($tokens, InputRef::class, $raw, $i),
            'outputs' => $this->parseSimpleRef($tokens, OutputRef::class, $raw, $i),
            'message' => $this->parseMessageRef(array_slice($tokens, $i), $raw),
            'self' => (function () use ($tokens, $raw, $i) {
                if (count(array_slice($tokens, $i)) !== 1) {
                    $this->fail("Malformed \$self reference: {$raw}", $raw, $tokens[$i + 1]);
                }

                return new SelfRef();
            })(),
            'url', 'method', 'statusCode' => $this->parseHttpMeta($tokens, $raw, $i),
            'steps' => $this->parseStepRef($tokens, $raw, $i),
            'workflows' => $this->parseWorkflowRef($tokens, $raw, $i),
            'sourceDescriptions' => $this->parseSourceRef($tokens, $raw, $i),
            'components' => $this->parseComponentRef($tokens, $raw, $i),
            'request', 'response' => new StepRef(
                null,
                $this->parseHttpPart(array_slice($tokens, $i + 1), $head->value === 'request' ? RequestPart::class : ResponsePart::class, $raw),
            ),
            default => $this->fail("Unknown root '{$head->value}' in expression: {$raw}", $raw, $head),
        };
    }

    /**
     * @param  list<Token>  $tokens
     * @param  class-string<InputRef|OutputRef>  $refClass
     */
    private function parseSimpleRef(array $tokens, string $refClass, string $raw, int $i): InputRef|OutputRef
    {
        // keyword . name [ # /pointer ]  (3 tokens, or 4+ with a JSON Pointer suffix)
        $rest = array_slice($tokens, $i);
        if (count($rest) < 3
            || $rest[1]->kind !== TokenKind::Dot
            || ($rest[2]->kind !== TokenKind::Name && $rest[2]->kind !== TokenKind::Keyword)) {
            $this->fail("Malformed reference: {$raw}", $raw, $this->firstUnexpected($rest, [
                1 => [TokenKind::Dot],
                2 => [TokenKind::Name, TokenKind::Keyword],
            ]));
        }

        if (count($rest) > 3) {
            if ($rest[3]->kind === TokenKind::Hash) {
                return new $refClass($rest[2]->value, $this->parseJsonPointer(array_slice($rest, 3), $raw));
            }

            $this->fail("Malformed reference: {$raw}", $raw, $rest[3]);
        }

        return new $refClass($rest[2]->value);
    }

    /** @param list<Token> $tokens */
    private function parseHttpMeta(array $tokens, string $raw, int $i): HttpMetaRef
    {
        $rest = array_slice($tokens, $i);
        if (count($rest) !== 1) {
            $this->fail("Malformed HTTP meta reference: {$raw}", $raw, $rest[1] ?? null);
        }
        /** @var 'url'|'method'|'statusCode' $field */
        $field = $rest[0]->value;

        return new HttpMetaRef($field);
    }

    /** @param list<Token> $tokens */
    private function parseStepRef(array $tokens, string $raw, int $i): StepRef
    {
        $rest = array_slice($tokens, $i);
        // steps . <name> . <sub>
        if (count($rest) < 5
            || $rest[1]->kind !== TokenKind::Dot
            || ($rest[2]->kind !== TokenKind::Name && $rest[2]->kind !== TokenKind::Keyword)
            || $rest[3]->kind !== TokenKind::Dot
            || ($rest[4]->kind !== TokenKind::Keyword && $rest[4]->kind !== TokenKind::Name)) {
            $this->fail("Malformed step reference: {$raw}", $raw, $this->firstUnexpected($rest, [
                1 => [TokenKind::Dot],
                2 => [TokenKind::Name, TokenKind::Keyword],
                3 => [TokenKind::Dot],
                4 => [TokenKind::Name, TokenKind::Keyword],
            ]));
        }
        $stepId = $rest[2]->value;
        $sub = $rest[4]->value;
        $tail = array_slice($rest, 5);

        return new StepRef($stepId, match ($sub) {
            'outputs' => $this->parseNamedPart($tail, OutputPart::class, $raw, allowPointer: true),
            'inputs' => $this->parseNamedPart($tail, InputPart::class, $raw),
            'request' => $this->parseHttpPart($tail, RequestPart::class, $raw),
            'response' => $this->parseHttpPart($tail, ResponsePart::class, $raw),
            // Spec shortcut: `$steps.<stepId>.<outputName>` (the `outputs`
            // infix is optional) - used by the official OAI examples.
            default => $this->parseOutputShortcut($tail, $sub, $raw),
        });
    }

    /**
     * @param  list<Token>  $tail
     */
    private function parseOutputShortcut(array $tail, string $name, string $raw): OutputPart
    {
        if ($tail === []) {
            return new OutputPart($name);
        }

        if ($tail[0]->kind === TokenKind::Hash) {
            return new OutputPart($name, $this->parseJsonPointer($tail, $raw));
        }

        $this->fail("Malformed output reference after step name in: {$raw}", $raw, $tail[0]);
    }

    /**
     * @param  list<Token>  $rest
     * @param  class-string<OutputPart|InputPart>  $cls
     */
    private function parseNamedPart(array $rest, string $cls, string $raw, bool $allowPointer = false): OutputPart|InputPart
    {
        if (count($rest) < 2 || $rest[0]->kind !== TokenKind::Dot || ($rest[1]->kind !== TokenKind::Name && $rest[1]->kind !== TokenKind::Keyword)) {
            $this->fail("Malformed reference: {$raw}", $raw, $this->firstUnexpected($rest, [
                0 => [TokenKind::Dot],
                1 => [TokenKind::Name, TokenKind::Keyword],
            ]));
        }

        if ($allowPointer && count($rest) > 2) {
            if ($rest[2]->kind === TokenKind::Hash && $cls === OutputPart::class) {
                return new OutputPart($rest[1]->value, $this->parseJsonPointer(array_slice($rest, 2), $raw));
            }

            $this->fail("Unexpected tokens after '{$rest[1]->value}' in: {$raw}", $raw, $rest[2]);
        }

        if (count($rest) !== 2) {
            $this->fail("Malformed reference: {$raw}", $raw, $rest[2] ?? null);
        }

        return new $cls($rest[1]->value);
    }

    /** @param list<Token> $rest */
    private function parseMessageRef(array $rest, string $raw): MessageRef
    {
        // message . header . <name>   |   message . payload [ # /pointer ]
        if (count($rest) < 3
            || $rest[1]->kind !== TokenKind::Dot
            || $rest[2]->kind !== TokenKind::Keyword) {
            $this->fail("Malformed message reference: {$raw}", $raw, $this->firstUnexpected($rest, [
                1 => [TokenKind::Dot],
                2 => [TokenKind::Keyword],
            ]));
        }

        $part = $rest[2]->value;
        $tail = array_slice($rest, 3);

        if ($part === 'header') {
            return new MessageRef('header', $this->parseHeaderName($tail, $raw), null);
        }

        if ($part === 'payload') {
            return new MessageRef('payload', null, $this->parseJsonPointer($tail, $raw));
        }

        $this->fail("Message part must be header or payload in: {$raw}", $raw, $rest[2]);
    }

    /**
     * @param  list<Token>  $rest
     * @param  class-string<RequestPart|ResponsePart>  $cls
     */
    private function parseHttpPart(array $rest, string $cls, string $raw): RequestPart|ResponsePart
    {
        // rest may be empty (bare $steps.s.request), or ". body[#/ptr]", ". header . name", ". url|method|statusCode"
        if ($rest === []) {
            return new $cls(null, null, null);
        }
        if ($rest[0]->kind !== TokenKind::Dot) {
            $this->fail("Expected '.' after part in: {$raw}", $raw, $rest[0]);
        }
        if (count($rest) < 2 || $rest[1]->kind !== TokenKind::Keyword) {
            $this->fail("Expected keyword after '.' in: {$raw}", $raw, $this->firstUnexpected($rest, [
                1 => [TokenKind::Keyword],
            ]));
        }
        $kw = $rest[1]->value;
        $tail = array_slice($rest, 2);

        return match ($kw) {
            'body' => new $cls('body', null, $this->parseJsonPointer($tail, $raw)),
            'header', 'query', 'path' => new $cls($kw, $this->parseHeaderName($tail, $raw), null),
            'url', 'method', 'statusCode' => (function () use ($cls, $kw, $tail, $raw) {
                if ($tail !== []) {
                    $this->fail("Unexpected tokens after '{$kw}' in: {$raw}", $raw, $tail[0]);
                }

                return new $cls($kw, null, null);
            })(),
            default => $this->fail("Unknown http part '{$kw}' in: {$raw}", $raw, $rest[1]),
        };
    }

    /** @param list<Token> $tail */
    private function parseJsonPointer(array $tail, string $raw): ?string
    {
        if ($tail === []) {
            return null;
        }
        if ($tail[0]->kind !== TokenKind::Hash) {
            $this->fail("Expected '#' before JSON pointer in: {$raw}", $raw, $tail[0]);
        }
        $out = '';
        $i = 1;
        $tokenCount = count($tail);
        while ($i < $tokenCount) {
            if ($tail[$i]->kind !== TokenKind::Slash) {
                $this->fail("Expected '/' in JSON pointer at token {$i} in: {$raw}", $raw, $tail[$i]);
            }
            $out .= '/';
            $i++;
            if ($i < $tokenCount && ($tail[$i]->kind === TokenKind::PointerSegment || $tail[$i]->kind === TokenKind::Name || $tail[$i]->kind === TokenKind::Keyword)) {
                $out .= $tail[$i]->value;
                $i++;
            }
        }

        return $out;
    }

    /** @param list<Token> $tail */
    private function parseHeaderName(array $tail, string $raw): string
    {
        if (count($tail) !== 2 || $tail[0]->kind !== TokenKind::Dot || ($tail[1]->kind !== TokenKind::Name && $tail[1]->kind !== TokenKind::Keyword)) {
            $at = $this->firstUnexpected($tail, [
                0 => [TokenKind::Dot],
                1 => [TokenKind::Name, TokenKind::Keyword],
            ]);
            if ($at === null && isset($tail[2])) {
                $at = $tail[2];
            }
            $this->fail("Expected '.name' after header in: {$raw}", $raw, $at);
        }

        return $tail[1]->value;
    }

    /** @param list<Token> $tokens */
    private function parseWorkflowRef(array $tokens, string $raw, int $i): WorkflowRef
    {
        $rest = array_slice($tokens, $i);

        // workflows . <name> . (inputs|outputs) . <name>
        if (count($rest) !== 7 || !$this->isWorkflowShape($rest)) {
            $at = $this->firstUnexpected($rest, [
                1 => [TokenKind::Dot],
                2 => [TokenKind::Name, TokenKind::Keyword],
                3 => [TokenKind::Dot],
                4 => [TokenKind::Keyword],
                5 => [TokenKind::Dot],
                6 => [TokenKind::Name, TokenKind::Keyword],
            ]);
            if ($at === null && isset($rest[7])) {
                $at = $rest[7];
            }
            $this->fail("Malformed workflow reference: {$raw}", $raw, $at);
        }

        $kind = $rest[4]->value;
        if ($kind !== 'inputs' && $kind !== 'outputs') {
            $this->fail("Workflow part must be inputs or outputs in: {$raw}", $raw, $rest[4]);
        }

        /** @var 'inputs'|'outputs' $kind */
        return new WorkflowRef($rest[2]->value, $kind, $rest[6]->value);
    }

    /** @param list<Token> $rest */
    private function isWorkflowShape(array $rest): bool
    {
        return $rest[1]->kind === TokenKind::Dot
            && $this->isNameOrKeyword($rest[2])
            && $rest[3]->kind === TokenKind::Dot
            && $rest[4]->kind === TokenKind::Keyword
            && $rest[5]->kind === TokenKind::Dot
            && $this->isNameOrKeyword($rest[6]);
    }

    private function isNameOrKeyword(Token $token): bool
    {
        return $token->kind === TokenKind::Name || $token->kind === TokenKind::Keyword;
    }

    /** @param list<Token> $tokens */
    private function parseSourceRef(array $tokens, string $raw, int $i): SourceRef
    {
        $rest = array_slice($tokens, $i);
        // sourceDescriptions . <name> [ . <raw tail> ]
        if (count($rest) < 3
            || $rest[1]->kind !== TokenKind::Dot
            || ($rest[2]->kind !== TokenKind::Name && $rest[2]->kind !== TokenKind::Keyword)) {
            $this->fail("Malformed sourceDescriptions reference: {$raw}", $raw, $this->firstUnexpected($rest, [
                1 => [TokenKind::Dot],
                2 => [TokenKind::Name, TokenKind::Keyword],
            ]));
        }
        $name = $rest[2]->value;
        if (count($rest) === 3) {
            return new SourceRef($name, null);
        }
        if ($rest[3]->kind !== TokenKind::Dot) {
            $this->fail("Expected '.' after source name in: {$raw}", $raw, $rest[3]);
        }
        // Reassemble the tail from token offsets.
        $tail = array_slice($rest, 4);
        $out = '';
        foreach ($tail as $t) {
            $out .= $t->kind === TokenKind::Dot ? '.' : $t->value;
        }

        return new SourceRef($name, $out === '' ? null : $out);
    }

    /** @param list<Token> $tokens */
    private function parseComponentRef(array $tokens, string $raw, int $i): ComponentRef
    {
        $rest = array_slice($tokens, $i);
        // components . <type> . <name>
        if (count($rest) !== 5
            || $rest[1]->kind !== TokenKind::Dot
            || ($rest[2]->kind !== TokenKind::Name && $rest[2]->kind !== TokenKind::Keyword)
            || $rest[3]->kind !== TokenKind::Dot
            || ($rest[4]->kind !== TokenKind::Name && $rest[4]->kind !== TokenKind::Keyword)) {
            $at = $this->firstUnexpected($rest, [
                1 => [TokenKind::Dot],
                2 => [TokenKind::Name, TokenKind::Keyword],
                3 => [TokenKind::Dot],
                4 => [TokenKind::Name, TokenKind::Keyword],
            ]);
            if ($at === null && isset($rest[5])) {
                $at = $rest[5];
            }
            $this->fail("Malformed components reference: {$raw}", $raw, $at);
        }

        return new ComponentRef($rest[2]->value, $rest[4]->value);
    }

    /**
     * Throw a syntax error carrying the offending token's byte offset, or the
     * end of the input when the failure is an absent token.
     */
    private function fail(string $message, string $raw, ?Token $at = null): never
    {
        throw new ExpressionSyntaxException($message, $raw, $at->offset ?? strlen($raw), '', 'expr.syntax');
    }

    /**
     * Find the first token that violates the expected kinds at its index.
     * Returns null when the expected token is missing (end of input) or every
     * inspected position matches.
     *
     * @param  list<Token>  $tokens
     * @param  array<int, list<TokenKind>>  $expected
     */
    private function firstUnexpected(array $tokens, array $expected): ?Token
    {
        foreach ($expected as $index => $kinds) {
            if (!isset($tokens[$index])) {
                return null;
            }
            if (!in_array($tokens[$index]->kind, $kinds, true)) {
                return $tokens[$index];
            }
        }

        return null;
    }

    public function parseOrError(string $raw): ExpressionAst|ExpressionSyntaxException
    {
        try {
            return $this->parse($raw);
        } catch (ExpressionSyntaxException $e) {
            return $e;
        }
    }

    public function projectReferences(string $raw): ?ExpressionReference
    {
        $result = $this->parseOrError($raw);

        if ($result instanceof ExpressionSyntaxException) {
            return null;
        }

        return $result->mapToExpressionReference();
    }
}
