<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

/**
 * Composition root for the runner execution graph. Laravel (and any other
 * framework host) supplies ports/config via AsyncGraphSeams and receives a
 * fully-wired AsyncExecutionGraph; the runner owns how everything is built.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface RunnerGraphBuilderInterface
{
    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph;
}
