<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Sync;

use Alama\Arazzo\Contracts\Spec\Workflow;

/**
 * Plans workflow-level `dependsOn` execution in one query.
 *
 * Deep module: callers ask for the dependencies of one workflow and receive a
 * post-ordered, deduplicated plan with the target excluded. Existence and
 * acyclicity belong to preflight validation in packages/document; the visited
 * set here is only a local invariant guard for unvalidated documents.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WorkflowDependencyResolver
{
    /** @var array<string, Workflow> */
    private array $byId = [];

    /** @var array<string, true> */
    private array $visited = [];

    /** @var list<string> */
    private array $order = [];

    /** @param list<Workflow> $workflows */
    public function __construct(array $workflows)
    {
        foreach ($workflows as $workflow) {
            $this->byId[$workflow->workflowId] = $workflow;
        }
    }

    /**
     * @return list<string> dependency ids in execution order, target excluded, each once
     */
    public function orderFor(string $workflowId): array
    {
        // Seeding the target keeps a dependency edge back to it from looping.
        $this->visited = [$workflowId => true];
        $this->order = [];

        $target = $this->byId[$workflowId] ?? null;
        if ($target !== null) {
            foreach ($target->dependsOn as $dependencyId) {
                $this->visit($dependencyId);
            }
        }

        return $this->order;
    }

    private function visit(string $workflowId): void
    {
        if (isset($this->visited[$workflowId])) {
            return;
        }
        $this->visited[$workflowId] = true;

        $workflow = $this->byId[$workflowId] ?? null;
        if ($workflow !== null) {
            foreach ($workflow->dependsOn as $dependencyId) {
                $this->visit($dependencyId);
            }
        }

        // Unknown ids are surfaced by the executor; preflight owns that rule.
        $this->order[] = $workflowId;
    }
}
