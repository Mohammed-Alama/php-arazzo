<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;

/**
 * Assembles the runner execution graph.
 *
 * The runner consumes the document package through its public face: source
 * resolution/fetching, preflight validation and OpenAPI operation resolution
 * all flow through {@see DocumentInterface}. Expression services flow
 * through the injected expression public face ({@see EvaluationEngineInterface}),
 * with the runner composing output extraction and schema validation itself.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExecutionGraphFactory
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

    public function createWorkflowExecutor(): WorkflowExecutor
    {
        $openApiExecutor = $this->openApiExecutor
            ?? throw new ExecutionException('No HTTP/OpenAPI executor configured; register alama/arazzo-protocol-http (Phase F1).');
        $outputExtractor = $this->outputExtractor
            ?? throw new ExecutionException('No output extractor configured; register alama/arazzo-protocol-http (Phase F1).');
        $schemaValidator = $this->schemaValidator
            ?? throw new ExecutionException('No response validator configured; register alama/arazzo-protocol-http (Phase F1).');

        return new WorkflowExecutor(
            new StepExecutor(
                $openApiExecutor,
                $this->operations->resolver,
                $this->evaluationEngine,
                $outputExtractor,
                $schemaValidator,
                inspector: $this->inspector,
            ),
            workflowEngine: new WorkflowEngine($this->evaluationEngine),
            preflight: $this->operations->document,
        );
    }
}
