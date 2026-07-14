<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowDependsOnExistsRule implements Rule
{
    public function code(): string { return 'workflow.dependson_exists'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->dependsOn as $j => $dep) {
                if (!is_scalar($dep)) {
                    $errors->error(
                        $this->code(),
                        "workflow '{$w->workflowId}' dependsOn item at index {$j} must be a string or integer.",
                        "/workflows/{$i}/dependsOn/{$j}",
                    );
                    continue;
                }
                if (!isset($symbols->workflows[(string)$dep])) {
                    $errors->error(
                        $this->code(),
                        "workflow '{$w->workflowId}' dependsOn '{$dep}' which is not declared.",
                        "/workflows/{$i}/dependsOn/{$j}",
                    );
                }
            }
        }
    }
}
