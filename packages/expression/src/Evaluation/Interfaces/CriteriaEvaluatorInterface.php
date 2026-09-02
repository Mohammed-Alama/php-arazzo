<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Interfaces;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;

interface CriteriaEvaluatorInterface
{
    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;
}
