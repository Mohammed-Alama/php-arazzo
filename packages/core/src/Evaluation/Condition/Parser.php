<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Condition;

use Alama\Arazzo\Evaluation\Condition\Ast\Comparison;
use Alama\Arazzo\Evaluation\Condition\Ast\ConditionNode;
use Alama\Arazzo\Evaluation\Condition\Ast\Literal;
use Alama\Arazzo\Evaluation\Condition\Ast\LogicalOp;
use Alama\Arazzo\Evaluation\Condition\Ast\RuntimeExpr;
use Alama\Arazzo\Evaluation\Condition\Ast\UnaryNot;
use Alama\Arazzo\Expression\Expression;

final class Parser
{
    private const TERMINAL_KINDS = [
        TokenKind::Number,
        TokenKind::String,
        TokenKind::Ident,
        TokenKind::Expr,
    ];

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

        while ($this->isNext(TokenKind::Or)) {
            $this->advance();
            $left = new LogicalOp(LogicalOperator::Or, $left, $this->parseAnd());
        }

        return $left;
    }

    private function parseAnd(): ConditionNode
    {
        $left = $this->parseComparison();

        while ($this->isNext(TokenKind::And)) {
            $this->advance();
            $left = new LogicalOp(LogicalOperator::And, $left, $this->parseComparison());
        }

        return $left;
    }

    private function parseComparison(): ConditionNode
    {
        $left = $this->parseOperand();

        $token = $this->peek();
        if ($token !== null && ($operator = $this->comparisonOperator($token->kind)) !== null) {
            $this->advance();

            return new Comparison($operator, $left, $this->parseOperand());
        }

        return $left;
    }

    private function comparisonOperator(TokenKind $kind): ?ComparisonOperator
    {
        return match ($kind) {
            TokenKind::Eq => ComparisonOperator::Eq,
            TokenKind::Neq => ComparisonOperator::Neq,
            TokenKind::Gt => ComparisonOperator::Gt,
            TokenKind::Gte => ComparisonOperator::Gte,
            TokenKind::Lt => ComparisonOperator::Lt,
            TokenKind::Lte => ComparisonOperator::Lte,
            default => null,
        };
    }

    private function parseOperand(): ConditionNode
    {
        if ($this->isNext(TokenKind::Not)) {
            $this->advance();

            return new UnaryNot($this->parseOperand());
        }

        $token = $this->peek();
        if ($token === null) {
            throw new ConditionSyntaxException('Unexpected end of condition.');
        }

        if ($token->kind === TokenKind::LParen) {
            return $this->parseGroup();
        }

        if (in_array($token->kind, self::TERMINAL_KINDS, true)) {
            return $this->parseTerminal();
        }

        throw new ConditionSyntaxException("Unexpected '{$token->value}' in condition.");
    }

    private function parseTerminal(): Literal|RuntimeExpr
    {
        $token = $this->advance();

        $node = match ($token->kind) {
            TokenKind::Number => new Literal(str_contains($token->value, '.') ? (float) $token->value : (int) $token->value),
            TokenKind::String => new Literal($token->value),
            TokenKind::Ident => $this->parseIdentLiteral($token),
            // Normalize both runtime-expression spellings to the canonical
            // `{$...}` form. The lexer captures `${token}` verbatim including
            // its closing brace, so re-wrapping must not duplicate it.
            default => new RuntimeExpr(
                new Expression(self::canonicalExpression($token->value)),
                $token->value,
            ),
        };

        $next = $this->peek();
        if ($next !== null && in_array($next->kind, self::TERMINAL_KINDS, true)) {
            throw new ConditionSyntaxException("Unexpected operand '{$next->value}' in condition.");
        }

        return $node;
    }

    private static function canonicalExpression(string $captured): string
    {
        // The lexer captures runtime expressions verbatim: either bare
        // `$steps.A.outputs.x` or the braced `${...}` spelling including its
        // closing brace. Normalize both to the canonical `{$...}` form.
        $body = str_ends_with($captured, '}') ? substr($captured, 0, -1) : $captured;

        if (str_starts_with($body, '${')) {
            $body = '$'.substr($body, 2);
        }

        return '{'.$body.'}';
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
        if ($closing === null || $closing->kind !== TokenKind::RParen) {
            throw new ConditionSyntaxException("Expected closing parenthesis in condition near '{$closing?->value}'.");
        }
        $this->advance();

        return $inner;
    }

    private function isNext(TokenKind $kind): bool
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
