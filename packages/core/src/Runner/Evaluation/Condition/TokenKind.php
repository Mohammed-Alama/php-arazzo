<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Condition;

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
}
