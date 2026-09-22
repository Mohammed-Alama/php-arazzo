<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Action\FailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessAction;

/**
 * The control/scheduling axis of a Step (D4).
 */
final readonly class StepFlow
{
    /**
     * @param  list<string>  $dependsOn
     * @param  list<SuccessAction|Reusable>  $onSuccess
     * @param  list<FailureAction|Reusable>  $onFailure
     * @param  list<SuccessAction|Reusable>  $onTimeout
     * @param  list<FailureAction|Reusable>  $onCancel
     */
    public function __construct(
        public array $dependsOn = [],
        public ?int $timeout = null, // duration in milliseconds
        public ?string $timeoutDuration = null, // ISO-8601 duration (1.2 proposal)
        public array $onSuccess = [],
        public array $onFailure = [],
        public array $onTimeout = [],
        public array $onCancel = [],
        public ?bool $strictValidation = null,
        public ?bool $idempotencyKey = null,
        public ?string $idempotencyHeader = null,
    ) {}
}
