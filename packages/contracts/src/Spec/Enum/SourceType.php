<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum SourceType: string
{
    case Openapi = 'openapi';
    case Arazzo = 'arazzo';
    case Asyncapi = 'asyncapi';
    case Wsdl = 'wsdl';
    case Protobuf = 'protobuf';
    case Graphql = 'graphql';
}
