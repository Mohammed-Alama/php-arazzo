<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

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
