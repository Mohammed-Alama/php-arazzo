<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Runner\Async\AsyncExecutionGraphAssembler;
use Alama\Arazzo\Runner\Async\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\OperationRuntime;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RunnerGraphBuilder implements RunnerGraphBuilderInterface
{
    public function __construct(
        private readonly EvaluationEngineInterface $evaluationEngine,
        private readonly ?OperationRuntime $operations = null,
    ) {}

    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        if ($this->operations === null) {
            throw new ExecutionException('RunnerGraphBuilder needs an OperationRuntime to assemble the async graph; worker-only hosts should use buildWorker().');
        }

        $assembler = new AsyncExecutionGraphAssembler(
            evaluationEngine: $this->evaluationEngine,
            operations: $this->operations,
        );

        return $assembler->assemble($seams);
    }

    public function buildWorker(AsyncGraphSeams $seams): StepExecutionWorker
    {
        $assembler = new AsyncExecutionGraphAssembler(
            evaluationEngine: $this->evaluationEngine,
            operations: $this->operations,
        );

        return $assembler->buildWorker($seams, $this->operations?->document);
    }
}
