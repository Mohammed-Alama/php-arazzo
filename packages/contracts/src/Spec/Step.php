<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Action\FailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessAction;

final readonly class Step
{
    /**
     * @param  list<Parameter|Reusable>  $parameters
     * @param  list<SuccessCriterion>  $successCriteria
     * @param  list<SuccessAction|Reusable>  $onSuccess
     * @param  list<FailureAction|Reusable>  $onFailure
     * @param  array<string,Expression|Selector|scalar|array<mixed>|null>  $outputs
     * @param  list<string>  $dependsOn
     */
    public function __construct(
        public string $stepId,
        public ?string $description,
        public ?string $operationId,
        public ?string $operationPath,
        public ?string $workflowId,
        public array $parameters,
        public ?RequestBody $requestBody,
        public array $successCriteria,
        public array $onSuccess,
        public array $onFailure,
        public array $outputs,
        public array $dependsOn = [],
        public ?string $action = null,
        public ?string $channelPath = null,
        public ?Expression $correlationId = null,
        public ?bool $strictValidation = null,
        public ?bool $idempotencyKey = null,
        public ?string $idempotencyHeader = null,
        public ?int $timeout = null, // duration in milliseconds
    ) {}
}
