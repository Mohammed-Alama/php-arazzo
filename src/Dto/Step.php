<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;

final readonly class Step
{
    /**
     * @param list<Parameter> $parameters
     * @param list<SuccessCriterion> $successCriteria
     * @param list<SuccessAction|Reusable> $onSuccess
     * @param list<FailureAction|Reusable> $onFailure
     * @param array<string,Expression> $outputs
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
    ) {
    }
}
