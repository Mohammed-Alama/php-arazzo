<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Action\FailureAction;
use Alama\LaravelArazzo\Dto\Action\SuccessAction;

final readonly class Components
{
    /**
     * @param array<string,array<string,mixed>> $inputs
     * @param array<string,Parameter> $parameters
     * @param array<string,SuccessAction> $successActions
     * @param array<string,FailureAction> $failureActions
     */
    public function __construct(
        public array $inputs,
        public array $parameters,
        public array $successActions,
        public array $failureActions,
    ) {
    }
}
