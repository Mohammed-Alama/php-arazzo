<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Exceptions;

use RuntimeException;

final class SchemaValidationException extends RuntimeException
{
    /**
     * @param  list<array{path: string, message: string}>  $violations
     */
    public function __construct(
        public readonly string $stepId,
        public readonly array $violations,
    ) {
        $count = count($violations);
        $message = "Response schema validation failed for step '{$stepId}' with {$count} violation(s).";
        if ($count > 0) {
            $first = $violations[0];
            $message .= " First violation at '{$first['path']}': {$first['message']}.";
        }

        parent::__construct($message);
    }
}
