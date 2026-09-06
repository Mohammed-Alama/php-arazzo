<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Enum;

/**
 * The kind of Arazzo reference an expression resolves to.
 *
 * Public value type: downstream packages discriminate on this instead of
 * type-matching on the (internal) expression AST.
 */
enum ReferenceKind
{
    case Input;
    case Output;
    case Step;
    case Workflow;
    case Source;
    case Component;
    case Message;
    case Self;
    case HttpMeta;
}
