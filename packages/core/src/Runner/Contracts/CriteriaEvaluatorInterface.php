<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\SuccessCriterion;
use Alama\Arazzo\Runner\WorkflowContext;

interface CriteriaEvaluatorInterface
{
    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool;

    /**
     * @param list<SuccessCriterion> $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool;
}
