<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;

/**
 * Named constructors for the decomposed Step model (D4).
 */
final readonly class StepFactory
{
    public static function http(
        string $stepId,
        ?string $description,
        StepFlow $flow,
        StepIo $io,
        ?string $operationId = null,
        ?string $operationPath = null,
    ): Step {
        return new Step($stepId, $description, StepTarget::http($operationId, $operationPath), $flow, $io);
    }

    public static function workflow(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $workflowId): Step
    {
        return new Step($stepId, $description, StepTarget::workflow($workflowId), $flow, $io);
    }

    public static function async(
        string $stepId,
        ?string $description,
        StepFlow $flow,
        StepIo $io,
        string $action,
        string $channelPath,
        ?Expression $correlationId = null,
    ): Step {
        return new Step($stepId, $description, StepTarget::async($action, $channelPath, $correlationId), $flow, $io);
    }

    public static function rpc(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $rpcMethod, RpcProtocol $rpcProtocol): Step
    {
        return new Step($stepId, $description, StepTarget::rpc($rpcMethod, $rpcProtocol), $flow, $io);
    }

    public static function graphql(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $graphqlOperation): Step
    {
        return new Step($stepId, $description, StepTarget::graphql($graphqlOperation), $flow, $io);
    }

    public static function interaction(string $stepId, ?string $description, StepFlow $flow, StepIo $io, Interaction $interaction): Step
    {
        return new Step($stepId, $description, StepTarget::interaction($interaction), $flow, $io);
    }

    public static function wsdl(string $stepId, ?string $description, StepFlow $flow, StepIo $io, string $operationName): Step
    {
        return new Step($stepId, $description, StepTarget::wsdl($operationName), $flow, $io);
    }
}
