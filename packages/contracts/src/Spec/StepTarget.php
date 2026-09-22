<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use InvalidArgumentException;

/**
 * The operation/protocol axis of a Step (D4). Exactly one variant is set
 * through the named static factories; the http factory enforces the 1.1
 * operationId/operationPath exclusivity at construction time.
 */
final readonly class StepTarget
{
    public function __construct(
        public ?string $operationId = null,
        public ?string $operationPath = null,
        public ?string $workflowId = null,
        public ?string $action = null,
        public ?string $channelPath = null,
        public ?Expression $correlationId = null,
        public ?string $operationName = null,
        public ?string $rpcMethod = null,
        public ?RpcProtocol $rpcProtocol = null,
        public ?string $graphqlOperation = null,
        public ?Interaction $interaction = null,
    ) {}

    public static function http(?string $operationId = null, ?string $operationPath = null): self
    {
        if (($operationId === null) === ($operationPath === null)) {
            throw new InvalidArgumentException(
                'http target requires exactly one of operationId or operationPath',
            );
        }

        return new self(operationId: $operationId, operationPath: $operationPath);
    }

    public static function workflow(string $workflowId): self
    {
        return new self(workflowId: $workflowId);
    }

    public static function async(string $action, string $channelPath, ?Expression $correlationId = null): self
    {
        return new self(action: $action, channelPath: $channelPath, correlationId: $correlationId);
    }

    public static function wsdl(string $operationName): self
    {
        return new self(operationName: $operationName);
    }

    public static function rpc(string $rpcMethod, RpcProtocol $rpcProtocol): self
    {
        return new self(rpcMethod: $rpcMethod, rpcProtocol: $rpcProtocol);
    }

    public static function graphql(string $graphqlOperation): self
    {
        return new self(graphqlOperation: $graphqlOperation);
    }

    public static function interaction(Interaction $interaction): self
    {
        return new self(interaction: $interaction);
    }
}
