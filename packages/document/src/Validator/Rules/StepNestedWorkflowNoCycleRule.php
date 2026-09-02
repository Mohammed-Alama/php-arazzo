<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

final class StepNestedWorkflowNoCycleRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $graph = [];
        foreach ($doc->workflows as $w) {
            $graph[$w->workflowId] = [];
            foreach ($w->steps as $s) {
                if ($s->workflowId !== null && !str_contains($s->workflowId, '.')) {
                    $graph[$w->workflowId][] = $s->workflowId;
                }
            }
        }

        foreach (array_keys($graph) as $node) {
            $visited = [];
            $stack = [];
            if ($this->hasCycle($node, $graph, $visited, $stack)) {
                $errors->error(
                    $this->code(),
                    "Circular nested workflow invocation detected involving '{$node}'.",
                    '/workflows', // Hard to pinpoint exact path, just reporting on workflows
                );
            }
        }
    }

    /**
     * @param  array<string,list<string>>  $graph
     * @param  array<string,true>  $visited
     * @param  array<string,true>  $stack
     */
    private function hasCycle(string $node, array $graph, array &$visited, array &$stack): bool
    {
        if (isset($stack[$node])) {
            return true;
        }
        if (isset($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $stack[$node] = true;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if ($this->hasCycle($neighbor, $graph, $visited, $stack)) {
                return true;
            }
        }

        unset($stack[$node]);

        return false;
    }

    public function code(): string
    {
        return 'step.nested_workflow_no_cycle';
    }
}
