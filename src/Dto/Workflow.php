<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;

final readonly class Workflow
{
    /**
     * @param array<string,mixed>|null $inputs
     * @param list<string> $dependsOn
     * @param list<Step> $steps
     * @param list<SuccessAction|Reusable> $successActions
     * @param list<FailureAction|Reusable> $failureActions
     * @param array<string,Expression> $outputs
     * @param list<Parameter> $parameters
     */
    public function __construct(
        public string $workflowId,
        public ?string $summary,
        public ?string $description,
        public ?array $inputs,
        public array $dependsOn,
        public array $steps,
        public array $successActions,
        public array $failureActions,
        public array $outputs,
        public array $parameters,
    ) {
    }
}
