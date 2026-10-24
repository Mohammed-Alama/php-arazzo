<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Support;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;

/**
 * Depth-first walk of the workflow dependency graph, reporting the first back
 * edge (a grey target) as a cycle. Split from the rule so the traversal state
 * (colours, indices, reported flag) is instance state instead of six method
 * parameters (#257).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WorkflowDependencyCycleWalker
{
    /** @var array<string,int> 0=white, 1=grey, 2=black */
    private array $color = [];

    /** @var array<string,int> */
    private array $indexOf = [];

    private bool $reported = false;

    public function __construct(
        private readonly SymbolTable $symbols,
        private readonly ErrorCollector $errors,
        private readonly string $code,
    ) {}

    public function run(ArazzoDocument $doc): void
    {
        foreach ($doc->workflows as $i => $w) {
            $this->color[$w->workflowId] = 0;
            $this->indexOf[$w->workflowId] = $i;
        }

        foreach ($doc->workflows as $w) {
            if (($this->color[$w->workflowId] ?? 0) === 0) {
                $this->visit($w->workflowId);
            }
            if ($this->reported) {
                break;
            }
        }
    }

    private function visit(string $node): void
    {
        if ($this->reported || !isset($this->symbols->workflows[$node])) {
            return;
        }

        $this->color[$node] = 1;
        foreach (array_keys($this->symbols->workflows[$node]->dependsOn) as $next) {
            if (!isset($this->color[$next])) {
                continue;
            }

            if ($this->color[$next] === 1) {
                $this->report($node, $next);

                return;
            }

            if ($this->color[$next] === 0) {
                $this->visit($next);
            }

            if ($this->reported) {
                return;
            }
        }

        $this->color[$node] = 2;
    }

    private function report(string $node, string $next): void
    {
        $i = $this->indexOf[$node] ?? 0;
        $this->errors->error(
            $this->code,
            "workflow.dependsOn cycle detected involving '{$node}' -> '{$next}'.",
            "/workflows/{$i}/dependsOn",
        );
        $this->reported = true;
    }
}
