<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console\Cli;

use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Spec\PendingCorrelation;

/**
 * In-process pending-correlation registry for CLI runs. The CLI worker never
 * writes correlations itself (that is the async outcome-handler's job in the
 * Laravel deployment), but the seams the worker drains against require the
 * port; this keeps it honest for the single-process lifecycle instead of
 * reaching for a store the CLI has no transport for.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class InProcessPendingCorrelationRegistry implements PendingCorrelationRegistryInterface
{
    /** @var array<string, PendingCorrelation> */
    private array $pending = [];

    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath, ?int $timeoutSeconds = null): void
    {
        $this->pending[$correlationId] = new PendingCorrelation(
            $correlationId,
            $executionId,
            $stepId,
            $channelPath,
            $timeoutSeconds === null ? null : (new \DateTimeImmutable())->modify("+{$timeoutSeconds} seconds"),
        );
    }

    public function findByCorrelationId(string $correlationId): ?PendingCorrelation
    {
        return $this->pending[$correlationId] ?? null;
    }

    public function consume(string $correlationId): void
    {
        unset($this->pending[$correlationId]);
    }

    public function existsForExecution(string $executionId): bool
    {
        foreach ($this->pending as $correlation) {
            if ($correlation->executionId === $executionId) {
                return true;
            }
        }

        return false;
    }
}
