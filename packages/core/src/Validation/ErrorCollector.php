<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation;

final class ErrorCollector
{
    /** @var list<Error> */
    private array $errors = [];

    /** @var list<Warning> */
    private array $warnings = [];

    public function error(string $code, string $message, string $path, ?int $line = null): void
    {
        $this->errors[] = new Error($code, $message, $path, $line);
    }

    public function warning(string $code, string $message, string $path, ?int $line = null): void
    {
        $this->warnings[] = new Warning($code, $message, $path, $line);
    }

    /** @return list<Error> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<Warning> */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
