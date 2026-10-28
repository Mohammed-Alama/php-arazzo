<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 *
 * Seeded, depth-bounded generator of valid strings from an AbnfGrammar.
 *
 * Used by the differential test: generate strings from the "expression" rule
 * and confirm the hand-written parser accepts them.
 */
final class AbnfGenerator
{
    public function __construct(
        private AbnfGrammar $grammar,
        private int $seed,
        private int $maxDepth = 8,
    ) {}

    /**
     * Generate a random string accepted by $rule.
     *
     * @throws AbnfGrammarException if $rule is not defined or depth is exceeded
     */
    public function generate(string $rule): string
    {
        $r = $this->grammar->rule($rule);
        if ($r === null) {
            throw new AbnfGrammarException("Undefined rule '$rule'");
        }

        return $this->generateConcatenation($this->pickBranch($r->elements), 0);
    }

    /**
     * @param  list<mixed>  $elements  one concatenation branch
     */
    private function generateConcatenation(array $elements, int $depth): string
    {
        $out = '';
        foreach ($elements as $element) {
            $out .= $this->generateElement($element, $depth);
        }

        return $out;
    }

    private function generateElement(mixed $element, int $depth): string
    {
        if (is_string($element)) {
            return $this->generate($element, $depth + 1);
        }

        if ($element instanceof AbnfCharVal) {
            return $element->value;
        }

        if ($element instanceof AbnfNumVal) {
            return $this->generateNumVal($element);
        }

        if (is_array($element)) {
            if (isset($element['group'])) {
                return $this->generateConcatenation($this->pickBranch($element['group']), $depth + 1);
            }

            if (isset($element['option'])) {
                if ($this->rand() % 2 === 0) {
                    return '';
                }

                return $this->generateConcatenation($this->pickBranch($element['option']), $depth + 1);
            }

            if (isset($element['repeat'])) {
                return $this->generateRepeat($element['repeat'], $element['element'], $depth);
            }
        }

        throw new AbnfGrammarException('Unknown element type in grammar');
    }

    private function generateNumVal(AbnfNumVal $val): string
    {
        $out = '';
        foreach ($val->values as $item) {
            if (is_array($item)) {
                // range: pick a random value in [from, to]
                $from = $item['from'];
                $to = $item['to'];
                $cp = $from + ($this->rand() % ($to - $from + 1));
            } else {
                $cp = $item;
            }

            $out .= mb_chr($cp, 'UTF-8');
        }

        return $out;
    }

    /**
     * @param  array{0: int, 1: int|null}  $repeat
     */
    private function generateRepeat(array $repeat, mixed $element, int $depth): string
    {
        $min = $repeat[0] ?? 0;
        $max = $repeat[1] ?? null;

        // Depth-bounded: at deeper levels, prefer the minimum count.
        $maxCount = $max !== null ? min($max, $min + 3) : $min + 3;
        $count = $min + ($this->rand() % max(1, $maxCount - $min + 1));

        // Cap depth for nullable repetitions to avoid runaway recursion.
        if ($depth >= $this->maxDepth) {
            $count = min($count, $min);
        }

        $out = '';
        for ($i = 0; $i < $count; $i++) {
            $out .= $this->generateElement($element, $depth + 1);
        }

        return $out;
    }

    /**
     * Pick one branch from an alternation (list of concatenations).
     *
     * @param  list<list<mixed>>  $elements
     * @return list<mixed>
     */
    private function pickBranch(array $elements): array
    {
        return $elements[$this->rand() % count($elements)];
    }

    private function rand(): int
    {
        $this->seed = ($this->seed * 1103515245 + 12345) & 0x7FFFFFFF;

        return $this->seed;
    }
}
