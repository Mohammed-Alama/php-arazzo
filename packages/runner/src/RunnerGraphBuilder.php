<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Runner\Execution\AsyncExecutionGraphAssembler;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RunnerGraphBuilder implements RunnerGraphBuilderInterface
{
    public function __construct(
        private readonly DocumentInterface $documents,
        private readonly ExpressionEngineInterface $engine,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        $assembler = new AsyncExecutionGraphAssembler(
            $this->documents,
            $this->engine,
            $this->httpClient,
            $this->requestFactory,
        );

        return $assembler->assemble($seams);
    }
}
