<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

interface CriteriaEvaluatorInterface
{
    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;
}
