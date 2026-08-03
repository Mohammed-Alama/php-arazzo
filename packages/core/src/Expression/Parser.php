<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Ast\HttpMetaRef;
use Alama\Arazzo\Expression\Ast\InputPart;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Ast\OutputPart;
use Alama\Arazzo\Expression\Ast\OutputRef;
use Alama\Arazzo\Expression\Ast\RequestPart;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\SourceRef;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Expression\Ast\WorkflowRef;

final class Parser
{
    public function __construct(private readonly Lexer $lexer = new Lexer())
    {
    }

    public function parse(string $raw): ExpressionAst
    {
        $tokens = $this->lexer->tokenize($raw);
        if ($tokens === []) {
            throw new ExpressionSyntaxException("Empty expression: {$raw}", '', 'expr.syntax');
        }

        $i = 0;
        // Optionally consume Dollar ($) token if it's there
        if ($tokens[$i]->kind === TokenKind::Dollar) {
            $i++;
            if (!isset($tokens[$i])) {
                throw new ExpressionSyntaxException("Expression ended after \$: {$raw}", '', 'expr.syntax');
            }
        }

        $head = $tokens[$i];
        if ($head->kind !== TokenKind::Keyword) {
            throw new ExpressionSyntaxException("Expression must start with a keyword: {$raw}", '', 'expr.syntax');
        }

        return match ($head->value) {
            'inputs' => $this->parseSimpleRef($tokens, InputRef::class, $raw, $i),
            'outputs' => $this->parseSimpleRef($tokens, OutputRef::class, $raw, $i),
            'url', 'method', 'statusCode' => $this->parseHttpMeta($tokens, $raw, $i),
            'steps' => $this->parseStepRef($tokens, $raw, $i),
            'workflows' => $this->parseWorkflowRef($tokens, $raw, $i),
            'sourceDescriptions' => $this->parseSourceRef($tokens, $raw, $i),
            'components' => $this->parseComponentRef($tokens, $raw, $i),
            'request', 'response' => throw new ExpressionSyntaxException(
                "Bare \${$head->value} must appear inside a \$steps.* expression: {$raw}", '', 'expr.syntax',
            ),
            default => throw new ExpressionSyntaxException("Unknown root '{$head->value}' in expression: {$raw}", '', 'expr.syntax'),
        };
    }

    /**
     * @param list<Token> $tokens
     * @param class-string<InputRef|OutputRef> $refClass
     */
    private function parseSimpleRef(array $tokens, string $refClass, string $raw, int $i): InputRef|OutputRef
    {
        // keyword . name  (expect exactly 3 tokens from $i)
        $rest = array_slice($tokens, $i);
        if (count($rest) !== 3
            || $rest[1]->kind !== TokenKind::Dot
            || ($rest[2]->kind !== TokenKind::Name && $rest[2]->kind !== TokenKind::Keyword)) {
            throw new ExpressionSyntaxException("Malformed reference: {$raw}", '', 'expr.syntax');
        }

        return new $refClass($rest[2]->value);
    }

    /** @param list<Token> $tokens */
    private function parseHttpMeta(array $tokens, string $raw, int $i): HttpMetaRef
    {
        $rest = array_slice($tokens, $i);
        if (count($rest) !== 1) {
            throw new ExpressionSyntaxException("Malformed HTTP meta reference: {$raw}", '', 'expr.syntax');
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
            || $rest[4]->kind !== TokenKind::Keyword) {
            throw new ExpressionSyntaxException("Malformed step reference: {$raw}", '', 'expr.syntax');
        }
        $stepId = $rest[2]->value;
        $sub = $rest[4]->value;
        $tail = array_slice($rest, 5);

        return new StepRef($stepId, match ($sub) {
            'outputs' => $this->parseNamedPart($tail, OutputPart::class, $raw),
            'inputs' => $this->parseNamedPart($tail, InputPart::class, $raw),
            'request' => $this->parseHttpPart($tail, RequestPart::class, $raw),
            'response' => $this->parseHttpPart($tail, ResponsePart::class, $raw),
            default => throw new ExpressionSyntaxException("Unknown step part '{$sub}' in: {$raw}", '', 'expr.syntax'),
        });
    }

    /**
     * @param list<Token> $rest
     * @param class-string<OutputPart|InputPart> $cls
     */
    private function parseNamedPart(array $rest, string $cls, string $raw): OutputPart|InputPart
    {
        if (count($rest) !== 2 || $rest[0]->kind !== TokenKind::Dot || ($rest[1]->kind !== TokenKind::Name && $rest[1]->kind !== TokenKind::Keyword)) {
            throw new ExpressionSyntaxException("Malformed reference: {$raw}", '', 'expr.syntax');
        }

        return new $cls($rest[1]->value);
    }

    /**
     * @param list<Token> $rest
     * @param class-string<RequestPart|ResponsePart> $cls
     */
    private function parseHttpPart(array $rest, string $cls, string $raw): RequestPart|ResponsePart
    {
        // rest may be empty (bare $steps.s.request), or ". body[#/ptr]", ". header . name", ". url|method|statusCode"
        if ($rest === []) {
            return new $cls(null, null, null);
        }
        if ($rest[0]->kind !== TokenKind::Dot) {
            throw new ExpressionSyntaxException("Expected '.' after part in: {$raw}", '', 'expr.syntax');
        }
        if (count($rest) < 2 || $rest[1]->kind !== TokenKind::Keyword) {
            throw new ExpressionSyntaxException("Expected keyword after '.' in: {$raw}", '', 'expr.syntax');
        }
        $kw = $rest[1]->value;
        $tail = array_slice($rest, 2);

        return match ($kw) {
            'body' => new $cls('body', null, $this->parseJsonPointer($tail, $raw)),
            'header' => new $cls('header', $this->parseHeaderName($tail, $raw), null),
            'url', 'method', 'statusCode' => (function () use ($cls, $kw, $tail, $raw) {
                if ($tail !== []) {
                    throw new ExpressionSyntaxException("Unexpected tokens after '{$kw}' in: {$raw}", '', 'expr.syntax');
                }

                return new $cls($kw, null, null);
            })(),
            default => throw new ExpressionSyntaxException("Unknown http part '{$kw}' in: {$raw}", '', 'expr.syntax'),
        };
    }

    /** @param list<Token> $tail */
    private function parseJsonPointer(array $tail, string $raw): ?string
    {
        if ($tail === []) {
            return null;
        }
        if ($tail[0]->kind !== TokenKind::Hash) {
            throw new ExpressionSyntaxException("Expected '#' before JSON pointer in: {$raw}", '', 'expr.syntax');
        }
        $out = '';
        $i = 1;
        while ($i < count($tail)) {
            if ($tail[$i]->kind !== TokenKind::Slash) {
                throw new ExpressionSyntaxException("Expected '/' in JSON pointer at token {$i} in: {$raw}", '', 'expr.syntax');
            }
            $out .= '/';
            $i++;
            if ($i < count($tail) && ($tail[$i]->kind === TokenKind::PointerSegment || $tail[$i]->kind === TokenKind::Name || $tail[$i]->kind === TokenKind::Keyword)) {
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
            throw new ExpressionSyntaxException("Expected '.name' after header in: {$raw}", '', 'expr.syntax');
        }

        return $tail[1]->value;
    }

    /** @param list<Token> $tokens */
    private function parseWorkflowRef(array $tokens, string $raw, int $i): WorkflowRef
    {
        $rest = array_slice($tokens, $i);
        // workflows . <name> . (inputs|outputs) . <name>
        if (count($rest) !== 7
            || $rest[1]->kind !== TokenKind::Dot
            || ($rest[2]->kind !== TokenKind::Name && $rest[2]->kind !== TokenKind::Keyword)
            || $rest[3]->kind !== TokenKind::Dot
            || $rest[4]->kind !== TokenKind::Keyword
            || $rest[5]->kind !== TokenKind::Dot
            || ($rest[6]->kind !== TokenKind::Name && $rest[6]->kind !== TokenKind::Keyword)) {
            throw new ExpressionSyntaxException("Malformed workflow reference: {$raw}", '', 'expr.syntax');
        }
        $kind = $rest[4]->value;
        if ($kind !== 'inputs' && $kind !== 'outputs') {
            throw new ExpressionSyntaxException("Workflow part must be inputs or outputs in: {$raw}", '', 'expr.syntax');
        }

        /** @var 'inputs'|'outputs' $kind */
        return new WorkflowRef($rest[2]->value, $kind, $rest[6]->value);
    }

    /** @param list<Token> $tokens */
    private function parseSourceRef(array $tokens, string $raw, int $i): SourceRef
    {
        $rest = array_slice($tokens, $i);
        // sourceDescriptions . <name> [ . <raw tail> ]
        if (count($rest) < 3
            || $rest[1]->kind !== TokenKind::Dot
            || ($rest[2]->kind !== TokenKind::Name && $rest[2]->kind !== TokenKind::Keyword)) {
            throw new ExpressionSyntaxException("Malformed sourceDescriptions reference: {$raw}", '', 'expr.syntax');
        }
        $name = $rest[2]->value;
        if (count($rest) === 3) {
            return new SourceRef($name, null);
        }
        if ($rest[3]->kind !== TokenKind::Dot) {
            throw new ExpressionSyntaxException("Expected '.' after source name in: {$raw}", '', 'expr.syntax');
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
            throw new ExpressionSyntaxException("Malformed components reference: {$raw}", '', 'expr.syntax');
        }

        return new ComponentRef($rest[2]->value, $rest[4]->value);
    }
}
