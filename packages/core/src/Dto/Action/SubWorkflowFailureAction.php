<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Action;

use Alama\Arazzo\Dto\Enum\ActionKind;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\SuccessCriterion;

final readonly class SubWorkflowFailureAction extends FailureAction
{
    /**
     * @param array<string, Expression|Selector|scalar|array<mixed>|null> $parameters
     * @param list<SuccessCriterion> $criteria
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
