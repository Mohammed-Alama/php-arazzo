<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class WorkflowDependsOnExistsRule implements Rule
{
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
                if (!isset($symbols->workflows[(string) $dep])) {
                    $errors->error(
                        $this->code(),
                        "workflow '{$w->workflowId}' dependsOn '{$dep}' which is not declared.",
                        "/workflows/{$i}/dependsOn/{$j}",
                    );
                }
            }
        }
    }

    public function code(): string
    {
        return 'workflow.dependson_exists';
    }
}
