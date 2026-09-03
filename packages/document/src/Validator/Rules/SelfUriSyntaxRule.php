<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class SelfUriSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion === SpecVersion::V1_0 || $doc->self === null) {
            return;
        }

        // Strip fragment before URL validation (filter_var rejects fragments in some cases).
        $withoutFragment = strtok($doc->self, '#') ?: '';
        if (filter_var($withoutFragment, FILTER_VALIDATE_URL) === false) {
            $errors->error(
                $this->code(),
                "Invalid \$self URI: '{$doc->self}'",
                '/$self',
            );
        }
    }

    public function code(): string
    {
        return 'document.self_uri_syntax';
    }
}
