<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Support;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Data\WorkflowSymbols;

final readonly class ExpressionSite
{
    /**
     * @param  'parameters'|'requestBody'|'criteria'|'outputs'|'onSuccess'|'onFailure'|'wf.parameters'|'wf.outputs'|'components'  $context
     */
    public function __construct(
        public string $pointer,
        public Expression $expression,
        public ?WorkflowSymbols $workflow,
        public ?string $currentStepId,
        public string $context,
    ) {}
}
