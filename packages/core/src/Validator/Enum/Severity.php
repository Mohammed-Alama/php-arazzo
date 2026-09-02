<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Enum;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
