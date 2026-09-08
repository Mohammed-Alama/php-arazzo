<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Enum;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
