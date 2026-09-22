<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum RpcProtocol: string
{
    case Grpc = 'grpc';
    case GrpcWeb = 'grpc-web';
    case Twirp = 'twirp';
    case Connect = 'connect';
}
