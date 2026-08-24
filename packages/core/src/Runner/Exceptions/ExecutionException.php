<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Exceptions;

use Alama\Arazzo\Support\Exceptions\ArazzoException;

final class ExecutionException extends ArazzoException
{
    public static function subWorkflowNotFound(string $workflowId): self
    {
        return new self(
            "Sub-workflow '{$workflowId}' not found in registry.",
            '/',
            'execution.subworkflow_not_found',
        );
    }

    public static function messageFactoryMissing(string $stepId): self
    {
        return new self(
            "AsyncAPI step '{$stepId}' cannot compile its message: no PSR-17 request/stream/URI factory was provided.",
            '/',
            'execution.message_factory_missing',
        );
    }

    public static function unresolvableChannelTarget(string $stepId, string $channelPath): self
    {
        return new self(
            "Send step '{$stepId}' has channelPath '{$channelPath}', which is not an absolute HTTP URI. "
            . 'Use an absolute broker/webhook URL, or wire AsyncAPI server resolution in the executor.',
            '/',
            'execution.unresolvable_channel_target',
        );
    }
}
