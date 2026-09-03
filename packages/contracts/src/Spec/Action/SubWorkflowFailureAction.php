<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Action;

use Alama\Arazzo\Contracts\Spec\Enum\ActionKind;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

final readonly class SubWorkflowFailureAction extends FailureAction
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
