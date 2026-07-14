<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation;

final readonly class Warning
{
    public function __construct(
        public string $code,
        public string $message,
        public string $path,
        public ?int $line = null,
    ) {}

    /** @return array{code:string,message:string,path:string,line:?int} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'path' => $this->path, 'line' => $this->line];
    }
}
