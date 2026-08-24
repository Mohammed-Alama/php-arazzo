<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

use Alama\Arazzo\Support\Exceptions\ArazzoException;

/**
 * Thrown by adapters when execution preflight fails: nothing has been
 * executed yet (no HTTP, queue, ledger, or registry writes).
 */
final class PreflightFailureException extends ArazzoException
{
    public function __construct(
        string $message,
        public readonly ValidationResult $result,
    ) {
        parent::__construct($message, '', 'preflight.failed');
    }
}
