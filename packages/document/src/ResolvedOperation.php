<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;

/**
 * A fully resolved single-operation view over one described source.
 *
 * Two-axis: sourceType() (openapi/arazzo/asyncapi/wsdl/protobuf/graphql) x
 * optional rpcProtocol() (grpc/grpc-web/twirp/connect). binding() is derived.
 *
 * Relocated to alama/arazzo-protocol-http in F1.
 */
class ResolvedOperation
{
    /**
     * @param  array<string, mixed>  $rawDocument
     */
    public function __construct(
        public readonly SourceDescription $source,
        public readonly NormalizedOpenApiOperation $normalized,
        public readonly OpenApi $openApi,
        public readonly array $rawDocument,
        public readonly Operation $cebeOperation,
        public readonly ?RpcProtocol $rpcProtocol = null,
        public readonly ?string $operationName = null,
        public readonly ?string $rpcMethod = null,
        public readonly ?string $graphqlOperation = null,
        public readonly ?Interaction $interaction = null,
    ) {}

    public function sourceType(): SourceType
    {
        return $this->source->type;
    }

    public function binding(): string
    {
        if ($this->rpcProtocol !== null) {
            return $this->rpcProtocol->value;
        }

        return match ($this->source->type) {
            SourceType::Openapi => 'http',
            SourceType::Arazzo => 'arazzo',
            SourceType::Asyncapi => 'asyncapi',
            SourceType::Wsdl => 'soap',
            SourceType::Protobuf => 'protobuf',
            SourceType::Graphql => 'graphql',
        };
    }
}
