<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

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
