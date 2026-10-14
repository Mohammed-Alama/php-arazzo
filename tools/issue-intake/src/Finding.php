<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

/**
 * One structural violation found in an issue body, labels or level links.
 *
 * `code` is the stable, machine-readable identity the shim and CI branch on;
 * `message` is the human-facing, actionable text surfaced to the agent.
 */
final readonly class Finding
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}
