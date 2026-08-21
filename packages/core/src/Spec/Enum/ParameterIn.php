<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Enum;

enum ParameterIn: string
{
    case Path = 'path';
    case Query = 'query';
    case Header = 'header';
    case Cookie = 'cookie';
    case Body = 'body';
    case Querystring = 'querystring';
}
