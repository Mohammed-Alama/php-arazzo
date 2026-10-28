<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 *
 * Generates single-character mutations of an input string drawn from ABNF
 * grammar-relevant characters ($ . # / ~ { } [ ] plus a few letters).
 *
 * Used by the differential test to compare the hand-written parser against
 * the ABNF oracle: for each mutated input, both must agree on accept/reject.
 */
final class AbnfMutator
{
    /** @var list<int> */
    private array $mutationChars;

    public function __construct(
        private readonly int $seed,
    ) {
        $this->mutationChars = $this->buildMutationChars();
    }

    /**
     * @return list<int>
     */
    private function buildMutationChars(): array
    {
        $chars = [ord('$'), ord('.'), ord('#'), ord('/'), ord('~'), ord('{'), ord('}'), ord('['), ord(']')];

        foreach (range(ord('a'), ord('z')) as $c) {
            $chars[] = $c;
        }

        return array_unique($chars);
    }

    /**
     * Generate a single-character mutation of $input: one insert, delete, or
     * replace at a pseudo-random position determined by $seed and $input.
     *
     * Returns null if $input is empty and no mutation applies.
     */
    public function mutate(string $input): ?string
    {
        $len = mb_strlen($input, 'UTF-8');
        $codePoints = $this->toCodePoints($input);

        $seed = ($this->seed * 31 + $len) & 0x7FFFFFFF;
        $op = $seed % 3; // 0=insert, 1=delete, 2=replace
        $pos = intdiv($seed, 3) % max(1, $len + 1);
        $charIndex = intdiv($seed, 3 * max(1, $len + 1)) % count($this->mutationChars);
        $cp = $this->mutationChars[$charIndex];

        switch ($op) {
            case 0: // insert
                array_splice($codePoints, $pos, 0, [$cp]);
                break;
            case 1: // delete
                if ($len === 0) {
                    return null;
                }
                array_splice($codePoints, $pos, 1);
                break;
            case 2: // replace
                if ($len === 0) {
                    return null;
                }
                $codePoints[$pos] = $cp;
                break;
        }

        return $this->fromCodePoints($codePoints);
    }

    /**
     * @param  int[]  $codePoints
     */
    private function fromCodePoints(array $codePoints): string
    {
        $out = '';
        foreach ($codePoints as $cp) {
            $out .= mb_chr($cp, 'UTF-8');
        }

        return $out;
    }

    /**
     * @return int[]
     */
    private function toCodePoints(string $input): array
    {
        $codePoints = [];
        $len = mb_strlen($input, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $codePoints[] = mb_ord(mb_substr($input, $i, 1, 'UTF-8'), 'UTF-8');
        }

        return $codePoints;
    }
}
