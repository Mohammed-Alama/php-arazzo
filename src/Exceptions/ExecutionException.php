<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

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
}
