<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Runner\Async\StepExecutionWorker;

/**
 * Composition root for the runner execution graph. Laravel (and any other
 * framework host) supplies ports/config via AsyncGraphSeams and receives a
 * fully-wired AsyncExecutionGraph; the runner owns how everything is built.
 *
 * Worker-only hosts (the CLI drain) use {@see self::buildWorker()} — it needs
 * no protocol value seams and returns the same canonical async worker.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface RunnerGraphBuilderInterface
{
    public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph;

    public function buildWorker(AsyncGraphSeams $seams): StepExecutionWorker;
}
