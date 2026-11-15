<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

use Symfony\Component\Process\Process;

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
