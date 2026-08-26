<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

use Alama\Arazzo\Spec\ArazzoDocument;

final readonly class ValidationResult
{
    /**
     * @param  list<Error>  $errors
     * @param  list<Warning>  $warnings
     */
    public function __construct(
        public ArazzoDocument $document,
        public array $errors,
        public array $warnings,
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
