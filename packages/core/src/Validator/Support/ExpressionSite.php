<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Support;

use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Expression\WorkflowSymbols;

final readonly class ExpressionSite
{
    /** @param 'parameters'|'requestBody'|'criteria'|'outputs'|'onSuccess'|'onFailure'|'wf.parameters'|'wf.outputs'|'components' $context */
    public function __construct(
        public string $pointer,
        public Expression $expression,
        public ?WorkflowSymbols $workflow,
        public ?string $currentStepId,
        public string $context,
    ) {
    }
}
