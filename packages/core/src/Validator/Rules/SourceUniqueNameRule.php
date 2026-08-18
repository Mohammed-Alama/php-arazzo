<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

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
