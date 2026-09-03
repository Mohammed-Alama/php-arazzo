<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

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
