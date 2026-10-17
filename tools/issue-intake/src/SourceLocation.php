<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

use InvalidArgumentException;

/**
 * A code reference: a repository-relative path plus an inclusive line range.
 *
 * Parsed from the `path:start-end` shorthand a reporter types, where the `end`
 * half is optional (a bare `path:88` is the single line 88). The range is what
 * makes a permalink point at *the evidence* rather than a whole file.
 */
final readonly class SourceLocation
{
    private function __construct(
        public string $path,
        public int $start,
        public int $end,
    ) {}

    /**
     * @throws InvalidArgumentException when the spec is not `<path>:<start>[-<end>]`.
     */
    public static function parse(string $spec): self
    {
        $spec = trim($spec);

        if ($spec === '') {
            throw new InvalidArgumentException('Empty location: expected <path>:<start>[-<end>].');
        }

        // Split on the *last* colon: the path may itself contain one, the line
        // range never does. Anything without a colon carries no line to pin.
        $colon = strrpos($spec, ':');

        if ($colon === false) {
            throw new InvalidArgumentException("Location \"{$spec}\" has no line: expected <path>:<start>[-<end>].");
        }

        $path = substr($spec, 0, $colon);
        $path = preg_replace('~^\./~', '', $path) ?? $path;
        $path = ltrim($path, '/');

        if ($path === '') {
            throw new InvalidArgumentException("Location \"{$spec}\" has no path.");
        }

        if (preg_match('/^(\d+)(?:-(\d+))?$/', substr($spec, $colon + 1), $matches) !== 1) {
            throw new InvalidArgumentException("Location \"{$spec}\" has a malformed line range: expected <start>[-<end>].");
        }

        $start = (int) $matches[1];
        $end = isset($matches[2]) ? (int) $matches[2] : $start;

        if ($start < 1) {
            throw new InvalidArgumentException("Location \"{$spec}\": line numbers are 1-based.");
        }

        if ($end < $start) {
            throw new InvalidArgumentException("Location \"{$spec}\": end line {$end} precedes start line {$start}.");
        }

        return new self($path, $start, $end);
    }

    /** A range spans more than one line; a point is a single line. */
    public function isRange(): bool
    {
        return $this->end > $this->start;
    }

    /** The human label a filer reads: `path:88-92` (or `path:88`). */
    public function label(): string
    {
        return $this->isRange()
            ? "{$this->path}:{$this->start}-{$this->end}"
            : "{$this->path}:{$this->start}";
    }

    /** The fragment GitHub scrolls to and highlights: `#L88-L92` (or `#L88`). */
    public function anchor(): string
    {
        return $this->isRange()
            ? "#L{$this->start}-L{$this->end}"
            : "#L{$this->start}";
    }
}
