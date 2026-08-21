<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

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
