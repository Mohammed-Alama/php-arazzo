<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Dependency\ImplicitDependencies;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepDependsOnNoCycleRule implements Rule
{
    private const string CROSS_WORKFLOW_PATTERN = '/^\$workflows\.(?<workflowId>.+)\.steps\.(?<stepId>.+)$/';

    /**
     * Arazzo 1.1 §5.8.5.1: a step in a separate Arazzo document is referenced
     * as $sourceDescriptions.<name>.<workflowId>.steps.<stepId>.
     */
    private const string CROSS_DOCUMENT_PATTERN = '/^\$sourceDescriptions\.[^.]+\.[^.]+\.steps\.[^.]+$/';

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $workflowsById = [];
        foreach ($doc->workflows as $workflow) {
            $workflowsById[$workflow->workflowId] = $workflow;
        }

        /** @var array<string,list<string>> $adjacency */
        $adjacency = [];
        /** @var array<string,array{0:int,1:int,2:string}> $nodeLocation */
        $nodeLocation = [];
        /** @var array<string,list<string>> $unresolved */
        $unresolved = [];

        $this->registerNodes($doc, $adjacency, $nodeLocation);
        $this->resolveEdges($doc, $workflowsById, $adjacency, $unresolved);

        $cycle = $this->findCycle($adjacency);
        if ($cycle !== null) {
            [$workflowIndex, $stepIndex] = $nodeLocation[$cycle[0]];
            $errors->error(
                'step.dependson_no_cycle',
                "step.dependsOn cycle detected involving: {$this->renderCycle($cycle)}.",
                "/workflows/{$workflowIndex}/steps/{$stepIndex}/dependsOn",
            );
        }

        foreach ($unresolved as $node => $refs) {
            [$workflowIndex, $stepIndex] = $nodeLocation[$node];
            foreach ($refs as $ref) {
                $errors->error(
                    'step.dependson_unresolved_reference',
                    "step.dependsOn reference '{$ref}' from step '{$nodeLocation[$node][2]}' does not exist in workflow.",
                    "/workflows/{$workflowIndex}/steps/{$stepIndex}/dependsOn",
                );
            }
        }
    }

    /**
     * @param  array<string,list<string>>  $adjacency
     * @param  array<string,array{0:int,1:int,2:string}>  $nodeLocation
     */
    private function registerNodes(
        ArazzoDocument $doc,
        array &$adjacency,
        array &$nodeLocation,
    ): void {
        foreach ($doc->workflows as $workflowIndex => $workflow) {
            foreach ($workflow->steps as $stepIndex => $step) {
                $node = "{$workflow->workflowId}::{$step->stepId}";
                $adjacency[$node] = [];
                $nodeLocation[$node] = [$workflowIndex, $stepIndex, $step->stepId];
            }
        }
    }

    /**
     * @param  array<string,Workflow>  $workflowsById
     * @param  array<string,list<string>>  $adjacency
     * @param  array<string,list<string>>  $unresolved
     */
    private function resolveEdges(
        ArazzoDocument $doc,
        array $workflowsById,
        array &$adjacency,
        array &$unresolved,
    ): void {
        foreach ($doc->workflows as $workflow) {
            foreach ($workflow->steps as $step) {
                $node = "{$workflow->workflowId}::{$step->stepId}";

                foreach ($step->flow->dependsOn as $ref) {
                    // Cross-document references point at another Arazzo
                    // description, which cannot be resolved offline. Accept a
                    // well-formed reference; malformed ones stay unresolved.
                    if (str_starts_with($ref, '$sourceDescriptions.')) {
                        if (preg_match(self::CROSS_DOCUMENT_PATTERN, $ref) !== 1) {
                            $unresolved[$node][] = $ref;
                        }

                        continue;
                    }

                    $target = $this->resolveTarget($ref, $workflow->workflowId, $workflowsById);
                    if ($target === null || !isset($adjacency[$target])) {
                        $unresolved[$node][] = $ref;

                        continue;
                    }
                    $adjacency[$node][] = $target;
                }

                foreach (ImplicitDependencies::fromStep($step) as $ref) {
                    $target = "{$workflow->workflowId}::{$ref}";
                    if (!isset($adjacency[$target])) {
                        $unresolved[$node][] = $ref;

                        continue;
                    }
                    $adjacency[$node][] = $target;
                }
            }
        }
    }

    /**
     * Resolve a dependsOn reference to a graph node key "workflowId::stepId".
     *
     * Cross-workflow references ($workflows.<id>.steps.<stepId>) resolve against
     * another workflow; plain stepIds resolve against the current workflow. The
     * caller must still confirm the target node exists (undefined step → null).
     *
     * @param  array<string,Workflow>  $workflowsById
     * @return string|null null when the reference cannot resolve at all
     */
    private function resolveTarget(string $ref, string $currentWorkflowId, array $workflowsById): ?string
    {
        if (!str_starts_with($ref, '$workflows.')) {
            return "{$currentWorkflowId}::{$ref}";
        }

        if (!preg_match(self::CROSS_WORKFLOW_PATTERN, $ref, $matches)) {
            return null; // malformed cross-workflow reference
        }

        $workflowId = $matches['workflowId'];
        $stepId = $matches['stepId'];
        if (!isset($workflowsById[$workflowId])) {
            return null; // undefined workflow
        }

        return $this->targetNode($workflowsById[$workflowId], $stepId);
    }

    /**
     * @return string|null null when the step is not declared in the workflow
     */
    private function targetNode(Workflow $workflow, string $stepId): ?string
    {
        foreach ($workflow->steps as $step) {
            if ($step->stepId === $stepId) {
                return "{$workflow->workflowId}::{$stepId}";
            }
        }

        return null;
    }

    /**
     * DFS colour-based cycle detection over a document-wide adjacency map.
     *
     * @param  array<string,list<string>>  $adjacency
     * @return list<string>|null the cycle path (start repeated at end) or null
     */
    private function findCycle(array $adjacency): ?array
    {
        /** @var array<string,int> $color 0=white,1=grey,2=black */
        $color = array_fill_keys(array_keys($adjacency), 0);

        $path = [];
        $cycle = null;

        $dfs = function (string $node) use (&$dfs, &$color, &$path, $adjacency, &$cycle): void {
            if ($cycle !== null) {
                return;
            }

            $color[$node] = 1;
            $path[] = $node;

            foreach ($adjacency[$node] as $next) {
                if ($color[$next] === 0) {
                    $dfs($next);
                } elseif ($color[$next] === 1) {
                    $cycleIndex = array_search($next, $path, true);
                    if ($cycleIndex !== false) {
                        $cycle = array_slice($path, $cycleIndex);
                        $cycle[] = $next;
                    }

                    return;
                }
            }

            $color[$node] = 2;
            array_pop($path);
        };

        foreach (array_keys($adjacency) as $node) {
            if ((($color[$node] === 0))) {
                $dfs($node);
            }
        }

        return $cycle;
    }

    /**
     * Render a cycle path for the error message. Steps are shown by stepId when
     * the cycle stays within one workflow; cross-workflow cycles are shown as
     * "workflowId.stepId" so the boundary is visible.
     *
     * @param  list<string>  $cycle  node keys, start repeated at end
     */
    private function renderCycle(array $cycle): string
    {
        $workflowIds = [];
        foreach ($cycle as $node) {
            $workflowIds[] = explode('::', $node)[0];
        }
        $crossWorkflow = count(array_unique($workflowIds)) > 1;

        $parts = [];
        foreach ($cycle as $node) {
            [$workflowId, $stepId] = explode('::', $node, 2);
            $parts[] = $crossWorkflow ? "{$workflowId}.{$stepId}" : $stepId;
        }

        return implode(' -> ', $parts);
    }

    public function code(): string
    {
        return 'step.dependson_validation';
    }
}
