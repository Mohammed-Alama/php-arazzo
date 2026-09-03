<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * Arazzo requires at least one Source Description: "an info field, a
 * sourceDescriptions field with at least one defined Source Description".
 */
final class DocumentSourceDescriptionsPresentRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->sourceDescriptions !== []) {
            return;
        }

        $errors->error(
            $this->code(),
            'Document must declare at least one sourceDescription.',
            '/sourceDescriptions',
        );
    }

    public function code(): string
    {
        return 'document.source_descriptions_present';
    }
}
