<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WorkflowDependsOnExistsRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->dependsOn as $j => $dep) {
                if (!isset($symbols->workflows[$dep])) {
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
