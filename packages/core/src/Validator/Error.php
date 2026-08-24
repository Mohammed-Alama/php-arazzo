<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

final readonly class Error
{
    public function __construct(
        public string $code,
        public string $message,
        public string $path,
        public ?int $line = null,
        public Severity $severity = Severity::Error,
    ) {
    }

    /** @return array{code:string,message:string,path:string,line:?int,severity:string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'path' => $this->path, 'line' => $this->line, 'severity' => $this->severity->value];
    }
}
