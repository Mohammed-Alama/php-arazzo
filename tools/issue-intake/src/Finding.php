<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

/**
 * One structural violation found in an issue body, labels or level links.
 *
 * `code` is the stable, machine-readable identity the shim and CI branch on;
 * `message` is the human-facing, actionable text surfaced to the agent.
 * `severity` is `error` (blocks conformance) or `warning` (reported but not
 * blocking); defaults to `error`. `section` is the id the finding is about,
 * not where it is reported — null for label/level findings.
 */
final readonly class Finding
{
    /**
     * @param  string  $code      Stable machine-readable finding code.
     * @param  string  $message   Human-facing, actionable text.
     * @param  string  $severity  'error' or 'warning' (defaults to 'error').
     * @param  string|null  $section  Section id the finding is about, or null.
     */
    public function __construct(
        public string $code,
        public string $message,
        public string $severity = 'error',
        public ?string $section = null,
    ) {}
}
