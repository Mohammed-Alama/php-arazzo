<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class DocumentArazzoVersionRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if (preg_match('/^1\.[01]\.\d+$/', $doc->arazzo) !== 1) {
            $errors->error(
                $this->code(),
                "Unsupported arazzo version '{$doc->arazzo}'; only '1.0.x' and '1.1.x' are supported.",
                '/arazzo',
            );
        }
    }

    public function code(): string
    {
        return 'document.arazzo_version';
    }
}
