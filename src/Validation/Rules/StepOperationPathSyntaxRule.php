<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepOperationPathSyntaxRule implements Rule
{
    public function code(): string { return 'step.operationpath_syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->operationPath === null) continue;
                $path = "/workflows/{$i}/steps/{$j}/operationPath";
                if (!str_contains($s->operationPath, '#')) {
                    $errors->error($this->code(), "operationPath '{$s->operationPath}' must contain '#' separating source and JSON Pointer.", $path);
                    continue;
                }
                [$src, $ptr] = explode('#', $s->operationPath, 2);
                if ($src === '' || !isset($symbols->sourceDescriptions[$src])) {
                    $errors->error($this->code(), "operationPath source '{$src}' is not a declared sourceDescription.", $path);
                }
                if ($ptr === '' || $ptr[0] !== '/') {
                    $errors->error($this->code(), "operationPath JSON Pointer '{$ptr}' must start with '/'.", $path);
                }
            }
        }
    }
}
