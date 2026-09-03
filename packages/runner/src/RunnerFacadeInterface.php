<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;

interface RunnerFacadeInterface
{
    /**
     * Execute a workflow by id against an Arazzo document.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed> the execution result as an array
     */
    public function run(ArazzoDocument $document, string $workflowId, array $inputs = []): array;
}
