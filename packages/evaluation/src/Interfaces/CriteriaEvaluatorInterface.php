<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Evaluation\Data\CriterionResultCollection;
use Alama\Arazzo\Evaluation\Events\CriterionEvaluatedEvent;

/**
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
interface CriteriaEvaluatorInterface
{
    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Evaluate criteria and report why each one reached its outcome.
     *
     * Short-circuits at the first criterion that is not `Passed`, so the last
     * result is the one that decided the outcome. Each evaluated criterion is
     * emitted as a {@see CriterionEvaluatedEvent}
     * through the configured PSR-14 dispatcher.
     *
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): CriterionResultCollection;
}
