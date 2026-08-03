<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;

final class DocumentInfoRequiredRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->info->title === '') {
            $errors->error($this->code(), 'info.title must be a non-empty string.', '/info/title');
        }
        if ($doc->info->version === '') {
            $errors->error($this->code(), 'info.version must be a non-empty string.', '/info/version');
        }
    }

    public function code(): string
    {
        return 'document.info_required';
    }
}
