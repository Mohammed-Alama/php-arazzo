<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

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

    public function add(Error|Warning $diagnostic): void
    {
        if ($diagnostic instanceof Error) {
            $this->errors[] = $diagnostic;

            return;
        }

        $this->warnings[] = $diagnostic;
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
