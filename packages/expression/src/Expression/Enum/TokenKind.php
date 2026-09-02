<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Enum;

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
