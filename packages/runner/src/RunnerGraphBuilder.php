<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Async\AsyncExecutionGraphAssembler;
use Alama\Arazzo\Runner\Execution\OperationRuntime;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RunnerGraphBuilder implements RunnerGraphBuilderInterface
{
    /** @phpstan-ignore-next-line constructor has 6 parameters, max 5 allowed by config */
    public function __construct(
        private readonly OperationRuntime $operations,
        private readonly EvaluationEngineInterface $evaluationEngine,
        private readonly ExpressionEngineInterface $inspector,
        private readonly ?OpenApiExecutorInterface $openApiExecutor = null,
        private readonly ?OutputExtractorInterface $outputExtractor = null,
        private readonly ?ResponseValidatorInterface $schemaValidator = null,
    ) {}

    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        /** @phpstan-ignore-next-line constructor has 6 parameters, 3 required */
        $assembler = new AsyncExecutionGraphAssembler(
            $this->operations,
            $this->evaluationEngine,
            $this->inspector,
            $this->openApiExecutor,
            $this->outputExtractor,
            $this->schemaValidator,
        );

        return $assembler->assemble($seams);
    }
}
