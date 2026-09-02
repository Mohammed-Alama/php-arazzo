<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Enum;

enum TokenKind: string
{
    case And = 'and';
    case Or = 'or';
    case Eq = 'eq';
    case Neq = 'neq';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
    case Not = 'not';
    case LParen = 'lparen';
    case RParen = 'rparen';
    case Number = 'number';
    case String = 'string';
    case Ident = 'ident';
    case Expr = 'expr';

    /**
     * Operator symbols mapped to their token kind. Two-character symbols
     * must precede their one-character prefixes so the lexer matches greedily.
     *
     * @var array<non-empty-string, self>
     */
    public const OPERATORS = [
        '&&' => self::And,
        '||' => self::Or,
        '==' => self::Eq,
        '!=' => self::Neq,
        '>=' => self::Gte,
        '<=' => self::Lte,
        '>' => self::Gt,
        '<' => self::Lt,
        '!' => self::Not,
        '(' => self::LParen,
        ')' => self::RParen,
    ];
}
