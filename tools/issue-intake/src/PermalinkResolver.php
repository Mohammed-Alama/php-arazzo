<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

use Symfony\Component\Process\Process;

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

    /** True when the object `sha` exists in the clone. */
    public function isKnown(string $sha): bool;

    /** True when `sha` is an ancestor of the checked-out HEAD. */
    public function isAncestor(string $sha): bool;
}

/**
 * A `PermalinkResolver` backed by `git cat-file` and `git merge-base`
 * against a repo root. A failed git call means "not verifiable" — never an
 * exception: the gate degrades to a warning instead of crashing CI.
 */
final class GitPermalinkResolver implements PermalinkResolver
{
    public function __construct(private readonly string $repoRoot) {}

    public function pathExistsAt(string $sha, string $path): bool
    {
        return $this->run(['cat-file', '-e', "{$sha}:{$path}"]);
    }

    public function isKnown(string $sha): bool
    {
        return $this->run(['cat-file', '-t', $sha]);
    }

    public function isAncestor(string $sha): bool
    {
        return $this->run(['merge-base', '--is-ancestor', $sha, 'HEAD']);
    }

    private function run(array $arguments): bool
    {
        try {
            $process = new Process(['git', '-C', $this->repoRoot, ...$arguments]);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $error) {
            return false;
        }
    }
}
