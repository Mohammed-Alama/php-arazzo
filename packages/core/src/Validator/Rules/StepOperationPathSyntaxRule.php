<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

final class StepOperationPathSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->operationPath === null) {
                    continue;
                }
                $path = "/workflows/{$i}/steps/{$j}/operationPath";
                if (!str_contains($s->operationPath, '#')) {
                    $errors->error($this->code(), "operationPath '{$s->operationPath}' must contain '#' separating source and JSON Pointer.", $path);

                    continue;
                }
                [$src, $ptr] = explode('#', $s->operationPath, 2);
                $sourceName = null;

                // The source part must be the runtime expression
                // `{$sourceDescriptions.NAME.url}`; extract NAME.
                if (preg_match('/^\{\$sourceDescriptions\.([^}]+)\.url\}$/', $src, $m) === 1) {
                    $sourceName = $m[1];
                }

                if ($sourceName === null) {
                    $errors->error($this->code(), "operationPath source '{$src}' must be the expression '{{\$sourceDescriptions.NAME.url}}'.", $path);

                    continue;
                }

                if (!isset($symbols->sourceDescriptions[$sourceName])) {
                    $errors->error($this->code(), "operationPath source '{$sourceName}' is not a declared sourceDescription.", $path);
                }
                if ($ptr === '' || $ptr[0] !== '/') {
                    $errors->error($this->code(), "operationPath JSON Pointer '{$ptr}' must start with '/'.", $path);
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.operationpath_syntax';
    }
}
