<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;

interface RunnerFacadeInterface
{
    /**
     * Execute a workflow by id against an Arazzo document and return the
     * compact execution summary.
     *
     * @param  array<string, mixed>  $inputs
     * @return array{workflowId: string, status: string, outputs: array<string, mixed>, stepsSpent: int, workflowCallStack: list<string>}
     */
    public function run(ArazzoDocument $document, string $workflowId, array $inputs = []): array;

    /**
     * Execute a workflow by id and include the per-step verdicts, for
     * consumers (cli/laravel) that render step-level outcomes.
     *
     * @param  array<string, mixed>  $inputs
     * @return array{workflowId: string, status: string, outputs: array<string, mixed>, stepsSpent: int, workflowCallStack: list<string>, steps: array<string, array{stepId: string, success: bool, outputs: array<int|string, mixed>, error: ?string}>}
     */
    public function execute(ArazzoDocument $document, string $workflowId, array $inputs = []): array;
}
