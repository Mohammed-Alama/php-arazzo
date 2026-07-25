<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Selector;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

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
