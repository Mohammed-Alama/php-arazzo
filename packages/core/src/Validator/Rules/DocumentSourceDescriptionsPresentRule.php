<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

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
