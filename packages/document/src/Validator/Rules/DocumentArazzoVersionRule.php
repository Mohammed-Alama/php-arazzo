<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class DocumentArazzoVersionRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if (preg_match('/^1\.[012]\.\d+$/', $doc->arazzo) !== 1) {
            $errors->error(
                $this->code(),
                "Unsupported arazzo version '{$doc->arazzo}'; expected 1.0.x, 1.1.x, or 1.2.x.",
                '/arazzo',
            );
        }
    }

    public function code(): string
    {
        return 'document.arazzo_version';
    }
}
