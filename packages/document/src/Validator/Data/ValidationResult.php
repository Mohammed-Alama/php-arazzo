<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Data;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;

final readonly class ValidationResult
{
    /**
     * @param  list<Error>  $errors
     * @param  list<Warning>  $warnings
     * @param  array<string, mixed>  $inputs  The default-populated input map (when validation ran against a schema).
     */
    public function __construct(
        public ArazzoDocument $document,
        public array $errors,
        public array $warnings,
        public array $inputs = [],
    ) {}

    /** @return array{valid:bool,errors:list<array{code:string,message:string,path:string,line:?int}>,warnings:list<array{code:string,message:string,path:string,line:?int}>} */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => array_map(fn (Error $e) => $e->toArray(), $this->errors),
            'warnings' => array_map(fn (Warning $w) => $w->toArray(), $this->warnings),
        ];
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
