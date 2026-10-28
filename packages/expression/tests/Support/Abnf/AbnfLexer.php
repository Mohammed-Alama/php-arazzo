<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 *
 * Character-level lexer for the ABNF subset. Tracks byte position so error
 * messages are descriptive.
 */
final class AbnfLexer
{
    private string $input;

    private int $pos = 0;

    public function __construct(string $input)
    {
        $this->input = $input;
    }

    public function position(): int
    {
        return $this->pos;
    }

    public function peek(): ?string
    {
        if ($this->pos >= strlen($this->input)) {
            return null;
        }

        return $this->input[$this->pos];
    }

    public function next(): void
    {
        $this->pos++;
    }

    public function clone(): self
    {
        $clone = new self($this->input);
        $clone->pos = $this->pos;

        return $clone;
    }

    public function restore(self $snapshot): void
    {
        $this->pos = $snapshot->pos;
    }
}
