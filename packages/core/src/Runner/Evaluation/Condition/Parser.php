<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition;

use Alama\Arazzo\Runner\Evaluation\Condition\Ast\Comparison;
use Alama\Arazzo\Runner\Evaluation\Condition\Ast\ConditionNode;
use Alama\Arazzo\Runner\Evaluation\Condition\Ast\Literal;
use Alama\Arazzo\Runner\Evaluation\Condition\Ast\LogicalOp;
use Alama\Arazzo\Runner\Evaluation\Condition\Ast\RuntimeExpr;
use Alama\Arazzo\Runner\Evaluation\Condition\Ast\UnaryNot;
use Alama\Arazzo\Spec\Expression;

final class Parser
{
    private Lexer $lexer;

    /** @var list<Token> */
    private array $tokens;

    private int $position = 0;

    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
    }

    public function parse(string $condition): ConditionNode
    {
        $this->tokens = $this->lexer->lex($condition);
        $this->position = 0;

        if ($this->tokens === []) {
            throw new ConditionSyntaxException('Condition is empty.');
        }

        $ast = $this->parseOr();

        $trailing = $this->peek();
        if ($trailing !== null) {
            throw new ConditionSyntaxException("Unexpected trailing input in condition near '{$trailing->value}'.");
        }

        return $ast;
    }

    private function parseOr(): ConditionNode
    {
        $left = $this->parseAnd();

        while ($this->isNext('or')) {
            $this->advance();
            $left = new LogicalOp('or', $left, $this->parseAnd());
        }

        return $left;
    }

    private function parseAnd(): ConditionNode
    {
        $left = $this->parseComparison();

        while ($this->isNext('and')) {
            $this->advance();
            $left = new LogicalOp('and', $left, $this->parseComparison());
        }

        return $left;
    }

    private function parseComparison(): ConditionNode
    {
        $left = $this->parseOperand();

        $token = $this->peek();
        if ($token !== null && in_array($token->kind, ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'], true)) {
            $this->advance();

            return new Comparison($token->kind, $left, $this->parseOperand());
        }

        return $left;
    }

    private function parseOperand(): ConditionNode
    {
        if ($this->isNext('not')) {
            $this->advance();

            return new UnaryNot($this->parseOperand());
        }

        $token = $this->peek();
        if ($token === null) {
            throw new ConditionSyntaxException('Unexpected end of condition.');
        }

        if ($token->kind === 'lparen') {
            return $this->parseGroup();
        }

        if (in_array($token->kind, ['number', 'string', 'ident', 'expr'], true)) {
            return $this->parseTerminal();
        }

        throw new ConditionSyntaxException("Unexpected '{$token->value}' in condition.");
    }

    private function parseTerminal(): Literal|RuntimeExpr
    {
        $token = $this->advance();

        $node = match ($token->kind) {
            'number' => new Literal(str_contains($token->value, '.') ? (float) $token->value : (int) $token->value),
            'string' => new Literal($token->value),
            'ident' => $this->parseIdentLiteral($token),
            'expr' => new RuntimeExpr(new Expression('{' . $token->value . '}'), $token->value),
            default => throw new ConditionSyntaxException("Unexpected '{$token->value}' in condition."),
        };

        $next = $this->peek();
        if ($next !== null && in_array($next->kind, ['number', 'string', 'ident', 'expr'], true)) {
            throw new ConditionSyntaxException("Unexpected operand '{$next->value}' in condition.");
        }

        return $node;
    }

    private function parseIdentLiteral(Token $token): Literal
    {
        $value = match ($token->value) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => throw new ConditionSyntaxException("Unknown identifier '{$token->value}' in condition; expected a runtime expression or literal."),
        };

        return new Literal($value);
    }

    private function parseGroup(): ConditionNode
    {
        $this->advance();
        $inner = $this->parseOr();

        $closing = $this->peek();
        if ($closing === null || $closing->kind !== 'rparen') {
            throw new ConditionSyntaxException("Expected closing parenthesis in condition near '{$closing?->value}'.");
        }
        $this->advance();

        return $inner;
    }

    private function isNext(string $kind): bool
    {
        $token = $this->peek();

        return $token !== null && $token->kind === $kind;
    }

    private function peek(): ?Token
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function advance(): Token
    {
        $token = $this->tokens[$this->position]
            ?? throw new ConditionSyntaxException('Unexpected end of condition.');

        $this->position++;

        return $token;
    }
}
