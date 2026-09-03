<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

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
