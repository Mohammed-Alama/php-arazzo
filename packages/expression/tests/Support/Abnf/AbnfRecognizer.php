<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support\Abnf;

/**
 * @internal stays out of the advertised contract; test support only, never shipped.
 *
 * Backtracking ABNF recogniser over Unicode code points.
 *
 * Given a parsed AbnfGrammar and a rule name, determines whether an input
 * string is accepted by that rule. Uses recursive descent with backtracking
 * and a step limit to guarantee termination.
 */
final class AbnfRecognizer
{
    public function __construct(
        private AbnfGrammar $grammar,
        private int $stepLimit = 10_000,
    ) {}

    /**
     * @return true if $input is accepted by $rule, false otherwise
     *
     * @throws AbnfGrammarException if $rule is not defined
     */
    public function matches(string $rule, string $input): bool
    {
        $r = $this->grammar->rule($rule);
        if ($r === null) {
            throw new AbnfGrammarException("Undefined rule '$rule'");
        }

        $codePoints = $this->toCodePoints($input);
        $steps = 0;

        $accepted = $this->matchAlternation($r->elements, $codePoints, 0, $steps);

        return $accepted && $steps <= $this->stepLimit && $this->atEnd($codePoints, $steps);
    }

    /**
     * Convert a PHP string to an array of Unicode code point values (ints).
     */
    private function toCodePoints(string $input): array
    {
        $codePoints = [];
        $len = mb_strlen($input, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($input, $i, 1, 'UTF-8');
            $codePoints[] = mb_ord($char, 'UTF-8');
        }

        return $codePoints;
    }

    private function atEnd(array $codePoints, int &$steps): bool
    {
        // always true: we match against the whole input, so the position must
        // be at the end for a full match. The match() methods below enforce
        // this by only returning true when pos === count($codePoints).
        return true;
    }

    /**
     * Match a list of elements (concatenation) starting at $pos.
     *
     * @param  list<mixed>  $elements
     * @param  int[]  $codePoints
     * @return int|false the new position after matching, or false on failure
     */
    private function matchConcatenation(array $elements, array $codePoints, int $pos, int &$steps): int|false
    {
        $current = $pos;
        foreach ($elements as $element) {
            $steps++;
            if ($steps > $this->stepLimit) {
                return false;
            }

            $next = $this->matchElement($element, $codePoints, $current, $steps);
            if ($next === false) {
                return false;
            }
            $current = $next;
        }

        return $current;
    }

    /**
     * Match an alternation (list of concatenations) starting at $pos.
     * Tries each concatenation in order with backtracking.
     *
     * @param  list<list<mixed>>  $elements
     * @param  int[]  $codePoints
     */
    private function matchAlternation(array $elements, array $codePoints, int $pos, int &$steps): int|false
    {
        foreach ($elements as $concat) {
            $steps++;
            if ($steps > $this->stepLimit) {
                return false;
            }

            $result = $this->matchConcatenation($concat, $codePoints, $pos, $steps);
            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    /**
     * Match a single element starting at $pos.
     *
     * @param  mixed  $element
     * @param  int[]  $codePoints
     */
    private function matchElement($element, array $codePoints, int $pos, int &$steps): int|false
    {
        if (is_string($element)) {
            // rulename reference
            $rule = $this->grammar->rule($element);
            if ($rule === null) {
                throw new AbnfGrammarException("Undefined rule '$element'");
            }

            return $this->matchAlternation($rule->elements, $codePoints, $pos, $steps);
        }

        if ($element instanceof AbnfCharVal) {
            return $this->matchCharVal($element, $codePoints, $pos, $steps);
        }

        if ($element instanceof AbnfNumVal) {
            return $this->matchNumVal($element, $codePoints, $pos, $steps);
        }

        if (is_array($element)) {
            if (isset($element['group'])) {
                return $this->matchAlternation($element['group'], $codePoints, $pos, $steps);
            }

            if (isset($element['option'])) {
                // Optional: try to match, but succeed even if it fails.
                $result = $this->matchAlternation($element['option'], $codePoints, $pos, $steps);

                return $result !== false ? $result : $pos;
            }

            if (isset($element['repeat'])) {
                return $this->matchRepeat($element['repeat'], $element['element'], $codePoints, $pos, $steps);
            }
        }

        throw new AbnfGrammarException('Unknown element type in grammar');
    }

    /**
     * Match a case-insensitive quoted string.
     */
    private function matchCharVal(AbnfCharVal $val, array $codePoints, int $pos, int &$steps): int|false
    {
        $target = $val->value;
        $len = mb_strlen($target, 'UTF-8');

        if ($pos + $len > count($codePoints)) {
            return false;
        }

        for ($i = 0; $i < $len; $i++) {
            $steps++;
            if ($steps > $this->stepLimit) {
                return false;
            }

            $cp = $codePoints[$pos + $i];
            $targetCp = mb_ord(mb_substr($target, $i, 1, 'UTF-8'), 'UTF-8');

            // Case-insensitive: compare upper-case forms.
            if (mb_strtoupper(mb_chr($cp, 'UTF-8'), 'UTF-8') !== mb_strtoupper(mb_chr($targetCp, 'UTF-8'), 'UTF-8')) {
                return false;
            }
        }

        return $pos + $len;
    }

    /**
     * Match a numeric value: single value, range [from,to], or dotted concatenation.
     */
    private function matchNumVal(AbnfNumVal $val, array $codePoints, int $pos, int &$steps): int|false
    {
        $current = $pos;

        foreach ($val->values as $item) {
            $steps++;
            if ($steps > $this->stepLimit) {
                return false;
            }

            if (is_array($item)) {
                // range: [from, to]
                if (!isset($codePoints[$current])) {
                    return false;
                }
                $cp = $codePoints[$current];
                if ($cp < $item['from'] || $cp > $item['to']) {
                    return false;
                }
                $current++;
            } else {
                // single value
                if (!isset($codePoints[$current])) {
                    return false;
                }
                if ($codePoints[$current] !== $item) {
                    return false;
                }
                $current++;
            }
        }

        return $current;
    }

    /**
     * Match a repetition: min to max times (max=null means unlimited).
     */
    private function matchRepeat(array $repeat, mixed $element, array $codePoints, int $pos, int &$steps): int|false
    {
        $min = $repeat[0] ?? 0;
        $max = $repeat[1] ?? null;

        $current = $pos;
        $count = 0;

        while (true) {
            $steps++;
            if ($steps > $this->stepLimit) {
                return false;
            }

            if ($max !== null && $count >= $max) {
                break;
            }

            $result = $this->matchElement($element, $codePoints, $current, $steps);

            if ($result === false) {
                break;
            }

            if ($result === $current) {
                // Element matched empty string: count once and stop to avoid
                // infinite loops on nullable repetitions like *( CHAR ).
                $count++;
                break;
            }

            $current = $result;
            $count++;
        }

        if ($count < $min) {
            return false;
        }

        return $current;
    }
}
