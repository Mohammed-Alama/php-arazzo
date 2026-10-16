<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

use InvalidArgumentException;

/**
 * A GitHub permalink: a blob URL pinned to a commit SHA, plus the foldable
 * block a §7 source-grounding entry pastes in.
 *
 * Pinning to a SHA is the whole point — a `blob/main/...` link rots the moment
 * the file moves, while `blob/<sha>/...` keeps pointing at the exact lines the
 * claim was made against, even after they shift.
 */
final readonly class Permalink
{
    /** Extensions worth naming in the fence; anything else gets a plain fence. */
    private const LANGUAGES = [
        'php' => 'php',
        'phtml' => 'php',
        'yml' => 'yaml',
        'yaml' => 'yaml',
        'json' => 'json',
        'md' => 'markdown',
        'sh' => 'bash',
        'bash' => 'bash',
        'xml' => 'xml',
        'neon' => 'neon',
        'dist' => '',
    ];

    /**
     * @throws InvalidArgumentException when the repo is not `owner/name` or the SHA is not hex.
     */
    public function __construct(
        private string $repo,
        private string $sha,
    ) {
        if (preg_match('~^[^/\s]+/[^/\s]+$~', $repo) !== 1) {
            throw new InvalidArgumentException("Repo \"{$repo}\" must be owner/name.");
        }

        if (preg_match('/^[0-9a-f]{7,40}$/', $sha) !== 1) {
            throw new InvalidArgumentException("Commit \"{$sha}\" must be a 7–40 character hex SHA.");
        }
    }

    /**
     * The `owner/name` slug a github.com remote points at, or null when the
     * remote is not one. Accepts the scp (`git@github.com:o/r.git`), https and
     * ssh URL forms git itself writes.
     */
    public static function slugFromRemote(string $remote): ?string
    {
        if (preg_match('~github\.com[:/]+([^/]+)/([^/]+?)(?:\.git)?/?$~', trim($remote), $matches) !== 1) {
            return null;
        }

        return $matches[1].'/'.$matches[2];
    }

    /** `https://github.com/owner/name/blob/<sha>/<path>#L<start>-L<end>`. */
    public function url(SourceLocation $location): string
    {
        return sprintf(
            'https://github.com/%s/blob/%s/%s%s',
            $this->repo,
            $this->sha,
            $location->path,
            $location->anchor(),
        );
    }

    /** The seven-character SHA a reader scans in the folded summary. */
    public function shortSha(): string
    {
        return substr($this->sha, 0, 7);
    }

    /**
     * The foldable evidence block: the permalink stays visible in the summary,
     * the snippet collapses behind `<details>` so the issue body stays readable.
     *
     * @param  string|null  $language  Fence language; inferred from the path when null.
     */
    public function details(SourceLocation $location, string $code, ?string $language = null): string
    {
        $language ??= self::languageFor($location->path);
        $fence = '```'.$language;

        return implode("\n", [
            sprintf(
                '<details><summary><a href="%s"><code>%s</code></a> @ <code>%s</code></summary>',
                $this->url($location),
                $location->label(),
                $this->shortSha(),
            ),
            '',
            $fence,
            rtrim($code, "\n"),
            '```',
            '',
            '</details>',
        ]);
    }

    private static function languageFor(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::LANGUAGES[$extension] ?? '';
    }
}
