<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Enum;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum TokenKind
{
    case Dollar;
    case Dot;
    case Hash;
    case Slash;
    case LeftBracket;
    case RightBracket;
    case Name;
    case PointerSegment;
    case Keyword;
}
