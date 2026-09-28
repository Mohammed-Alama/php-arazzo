<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Runner\Execution\AsyncExecutionGraphAssembler;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RunnerGraphBuilder implements RunnerGraphBuilderInterface
{
    public function __construct(
        private readonly OperationRuntime $operations,
        private readonly EvaluationEngineInterface $evaluationEngine,
        private readonly ExpressionEngineInterface $inspector,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        $assembler = new AsyncExecutionGraphAssembler(
            $this->operations,
            $this->evaluationEngine,
            $this->inspector,
            $this->httpClient,
            $this->requestFactory,
        );

        return $assembler->assemble($seams);
    }
}
