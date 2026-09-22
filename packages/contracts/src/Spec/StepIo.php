<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

/**
 * The data axis of a Step (D4).
 */
final readonly class StepIo
{
    /**
     * @param  list<Parameter|Reusable>  $parameters
     * @param  list<SuccessCriterion>  $successCriteria
     * @param  array<string,Expression|Selector|scalar|array<mixed>|null>  $outputs
     */
    public function __construct(
        public array $parameters = [],
        public ?RequestBody $requestBody = null,
        public array $successCriteria = [],
        public array $outputs = [],
    ) {}
}
