<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Exceptions;

use Alama\Arazzo\Support\Exceptions\ArazzoException;
use Alama\Arazzo\Validator\ValidationResult;

final class ValidationException extends ArazzoException
{
    public function __construct(public readonly ValidationResult $result)
    {
        $count = count($result->errors);
        parent::__construct("Arazzo document failed validation with {$count} error(s).", '', 'validation.failed');
    }
}
