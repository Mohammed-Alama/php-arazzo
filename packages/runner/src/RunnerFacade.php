<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Runner\Execution\Data\ExecutionResult;
use Alama\Arazzo\Runner\Execution\ExecutionGraphFactory;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

final class RunnerFacade implements RunnerFacadeInterface
{
    private WorkflowExecutor $executor;

    public function __construct(
        DocumentInterface $documents,
        ?ClientInterface $httpClient = null,
    ) {
        $this->executor = (new ExecutionGraphFactory($documents, $httpClient))->createWorkflowExecutor();
    }

    public function run(ArazzoDocument $document, string $workflowId, array $inputs = []): array
    {
        $result = $this->executor->execute($this->workflow($document, $workflowId), $document, $inputs);

        return $this->baseResult($result);
    }

    public function execute(ArazzoDocument $document, string $workflowId, array $inputs = []): array
    {
        $result = $this->executor->execute($this->workflow($document, $workflowId), $document, $inputs);

        $steps = [];
        foreach ($result->stepResults as $stepId => $stepResult) {
            $steps[$stepId] = [
                'stepId' => $stepResult->stepId,
                'success' => $stepResult->success,
                'outputs' => $stepResult->outputs,
                'error' => $stepResult->error?->getMessage(),
            ];
        }

        return [
            ...$this->baseResult($result),
            'steps' => $steps,
        ];
    }

    /** @return array{workflowId: string, status: string, outputs: array<string, mixed>, stepsSpent: int, workflowCallStack: list<string>} */
    private function baseResult(ExecutionResult $result): array
    {
        return [
            'workflowId' => $result->workflowId,
            'status' => $result->status,
            'outputs' => $result->outputs,
            'stepsSpent' => $result->stepsSpent,
            'workflowCallStack' => $result->workflowCallStack,
        ];
    }

    private function workflow(ArazzoDocument $document, string $workflowId): Workflow
    {
        foreach ($document->workflows as $candidate) {
            if ($candidate->workflowId === $workflowId) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf("unknown workflow '%s'", $workflowId));
    }
}
