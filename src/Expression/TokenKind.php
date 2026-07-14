<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Expression;

enum TokenKind
{
    case Dollar;
    case Dot;
    case Hash;
    case Slash;
    case Name;
    case PointerSegment;
    case Keyword;
}
