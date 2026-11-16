<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

/**
 * Verifies a commit-pinned permalink against a local clone.
 *
 * The engine is pure (spec D12): it never reaches the network. Every
 * question about whether a SHA is known or a path exists at it is answered
 * by the resolver injected into the engine, so the same checks run under
 * pytest fixtures, the CI gate and the gh shim with identical semantics.
 */
interface PermalinkResolver
{
    /** True when the file at `path` exists in the object `sha`. */
    public function pathExistsAt(string $sha, string $path): bool;

    /** The number of lines in the file at `path` in object `sha`, or null when unverifiable. */
    public function lineCount(string $sha, string $path): ?int;

    /** True when the object `sha` exists in the clone. */
    public function isKnown(string $sha): bool;

    /** True when `sha` is an ancestor of the checked-out HEAD. */
    public function isAncestor(string $sha): bool;
}
