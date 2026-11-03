<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Data;

use Alama\Arazzo\Evaluation\Enum\CriterionOutcome;
use ArrayIterator;

/**
 * The results of evaluating a list of success criteria, short-circuiting at
 * the first non-`Passed` result.
 *
 * Encapsulates the "criteria met" decision instead of leaving callers to
 * inspect the last element of a bare array.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class CriterionResultCollection
{
    /** @param  list<CriterionResult>  $results */
    public function __construct(
        public array $results,
    ) {}

    /**
     * Whether the criteria are met: an empty list passes, otherwise the last
     * evaluated result must be `Passed`. Because evaluation short-circuits at
     * the first non-passed criterion, the last result is the one that decided
     * the outcome.
     */
    public function criteriaMet(): bool
    {
        if ($this->results === []) {
            return true;
        }

        $lastKey = array_key_last($this->results);

        return $this->results[$lastKey]->outcome === CriterionOutcome::Passed;
    }

    public function count(): int
    {
        return count($this->results);
    }

    /** @return ArrayIterator<int, CriterionResult> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->results);
    }

    public function last(): ?CriterionResult
    {
        return $this->results === [] ? null : $this->results[array_key_last($this->results)];
    }
}
