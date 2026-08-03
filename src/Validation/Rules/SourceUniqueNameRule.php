<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SourceUniqueNameRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $seen = [];
        foreach ($doc->sourceDescriptions as $i => $s) {
            if (isset($seen[$s->name])) {
                $errors->error($this->code(), "Duplicate sourceDescription name '{$s->name}'.", "/sourceDescriptions/{$i}/name");
            }
            $seen[$s->name] = true;
        }
    }

    public function code(): string
    {
        return 'source.unique_name';
    }
}
