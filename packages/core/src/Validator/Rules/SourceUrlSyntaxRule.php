<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class SourceUrlSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->sourceDescriptions as $i => $s) {
            if (trim($s->url) === '') {
                $errors->error($this->code(), 'sourceDescription url must not be empty.', "/sourceDescriptions/{$i}/url");

                continue;
            }
            // Accept absolute URLs and relative paths (e.g., api.yaml).
            // A simple proxy for valid URI reference is that it contains no whitespace.
            if (preg_match('/\s/', $s->url) === 0) {
                continue;
            }
            $errors->error($this->code(), "sourceDescription url '{$s->url}' is not a valid URI or relative path.", "/sourceDescriptions/{$i}/url");
        }
    }

    public function code(): string
    {
        return 'source.url_syntax';
    }
}
