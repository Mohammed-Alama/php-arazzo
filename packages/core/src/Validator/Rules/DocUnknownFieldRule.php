<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class DocUnknownFieldRule implements Rule
{
    private const array KNOWN = ['arazzo', 'info', 'sourceDescriptions', 'workflows', 'components'];

    public function __construct(public readonly bool $strict = true)
    {
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->rawRoot === null) {
            return;
        }
        foreach ($doc->rawRoot as $k => $_) {
            if (!is_string($k)) {
                continue;
            }
            if (in_array($k, self::KNOWN, true) || str_starts_with($k, 'x-')) {
                continue;
            }
            $msg = "Unknown top-level field '{$k}'.";
            $path = '/' . str_replace(['~', '/'], ['~0', '~1'], $k);
            if ($this->strict) {
                $errors->error($this->code(), $msg, $path);
            } else {
                $errors->warning($this->code(), $msg, $path);
            }
        }
    }

    public function code(): string
    {
        return 'doc.unknown_field';
    }
}
