<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum InteractionMode: string
{
    case Form = 'form';
    case Redirect = 'redirect';
    case Acknowledge = 'acknowledge';
}
