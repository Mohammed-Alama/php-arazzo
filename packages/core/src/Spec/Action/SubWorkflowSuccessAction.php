<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Action;

use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Expression\Selector;
use Alama\Arazzo\Spec\Enum\ActionKind;
use Alama\Arazzo\Spec\SuccessCriterion;

final readonly class SubWorkflowSuccessAction extends SuccessAction
{
    /**
     * @param  array<string, Expression|Selector|scalar|array<mixed>|null>  $parameters
     * @param  list<SuccessCriterion>  $criteria
     */
    public function __construct(
        string $name,
        public string $workflowId,
        public array $parameters,
        array $criteria,
        public ?string $version = null,
    ) {
        parent::__construct($name, ActionKind::Invoke, $criteria);
    }
}
