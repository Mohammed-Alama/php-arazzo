<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Data;

use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Evaluation\Enum\CriterionOutcome;
use Alama\Arazzo\Evaluation\Enum\CriterionReason;

/**
 * The outcome of evaluating one success criterion.
 *
 * `message` carries the underlying error message when `outcome` is `Error`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class CriterionResult
{
    public function __construct(
        public SuccessCriterion $criterion,
        public CriterionOutcome $outcome,
        public CriterionReason $reason,
        public ?string $message = null,
    ) {}
}
