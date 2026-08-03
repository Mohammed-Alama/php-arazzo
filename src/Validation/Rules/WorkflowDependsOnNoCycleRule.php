<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowDependsOnNoCycleRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        /** @var array<string,int> $color 0=white,1=grey,2=black */
        $color = [];
        foreach ($doc->workflows as $w) {
            $color[$w->workflowId] = 0;
        }
        $indexOf = [];
        foreach ($doc->workflows as $i => $w) {
            $indexOf[$w->workflowId] = $i;
        }

        $reported = false;
        $dfs = function (string $node) use (&$dfs, &$color, $symbols, $errors, $indexOf, &$reported): void {
            if ($reported) {
                return;
            }
            if (!isset($symbols->workflows[$node])) {
                return;
            }
            $color[$node] = 1;
            foreach ($symbols->workflows[$node]->dependsOn as $next => $_) {
                if (!isset($color[$next])) {
                    continue;
                }
                if ($color[$next] === 1) {
                    $i = $indexOf[$node] ?? 0;
                    $errors->error(
                        $this->code(),
                        "workflow.dependsOn cycle detected involving '{$node}' -> '{$next}'.",
                        "/workflows/{$i}/dependsOn",
                    );
                    $reported = true;

                    return;
                }
                if ($color[$next] === 0) {
                    $dfs($next);
                }
                /** @phpstan-ignore if.alwaysFalse */
                if ($reported) {
                    return;
                }
            }
            $color[$node] = 2;
        };

        foreach ($doc->workflows as $w) {
            if (($color[$w->workflowId] ?? 0) === 0) {
                $dfs($w->workflowId);
            }
            if ($reported) {
                break;
            }
        }
    }

    public function code(): string
    {
        return 'workflow.dependson_no_cycle';
    }
}
