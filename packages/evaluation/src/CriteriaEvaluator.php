<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Evaluation\Data\CriterionResult;
use Alama\Arazzo\Evaluation\Data\CriterionResultCollection;
use Alama\Arazzo\Evaluation\Enum\CriterionOutcome;
use Alama\Arazzo\Evaluation\Enum\CriterionReason;
use Alama\Arazzo\Evaluation\Events\CriterionEvaluatedEvent;
use Alama\Arazzo\Evaluation\Interfaces\CriteriaEvaluatorInterface;
use Alama\Arazzo\Evaluation\Registries\CriterionEvaluatorRegistry;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\ExpressionDependencyProvider;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Registries\SelectorPluginRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Evaluates success criteria and reports why each one reached its outcome.
 *
 * Every evaluation failure is deterministic: the boolean convenience methods
 * return `false` and the detailed API records `Error` with a machine-readable
 * reason. That includes a plugin that throws, which is reported as
 * `PluginError` instead of propagating; this is the one deliberate change from
 * the previous behaviour.
 *
 * Each evaluated criterion is emitted as a {@see CriterionEvaluatedEvent}
 * through the injected PSR-14 dispatcher so telemetry can observe outcomes
 * without pulling them back out of the return value.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class CriteriaEvaluator implements CriteriaEvaluatorInterface
{
    private ConditionEvaluator $conditionEvaluator;

    private ?CriterionEvaluatorRegistry $criterionRegistry;

    private BuiltInCriterionEvaluator $builtIn;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        private ExpressionEngineInterface $expression,
        ?ConditionEvaluator $conditionEvaluator = null,
        ?CriterionEvaluatorRegistry $criterionRegistry = null,
        ?SelectorPluginRegistry $selectorRegistry = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->conditionEvaluator = $conditionEvaluator ?? new ConditionEvaluator($expression);
        $this->criterionRegistry = $criterionRegistry;
        $this->builtIn = new BuiltInCriterionEvaluator(
            $selectorRegistry ?? (new ExpressionDependencyProvider())->getSelectorPluginRegistry(),
            $this->conditionEvaluator,
        );
        $this->eventDispatcher = $eventDispatcher ?? new NullEventDispatcher();
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        // Default success behavior: an operation step without explicit criteria
        // passes on any 2xx response (community/spec-tooling convention).
        if ($this->hasOperationTarget($step) && $step->io->successCriteria === []) {
            $steps = $context->getSteps();
            $stepData = $steps[$step->stepId] ?? null;
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;

            return self::isSuccessStatusCode(is_array($response) ? ($response['statusCode'] ?? null) : null);
        }

        return $this->evaluateCriteria($step->io->successCriteria, $step, $context, $document)->criteriaMet();
    }

    /**
     * Evaluate criteria and report why each one reached its outcome.
     *
     * Short-circuits at the first criterion that is not `Passed`, so the last
     * result is the one that decided the outcome. Each evaluated criterion is
     * emitted as a {@see CriterionEvaluatedEvent}.
     *
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): CriterionResultCollection
    {
        $results = [];

        foreach ($criteria as $criterion) {
            $result = $this->evaluateCriterion($criterion, $step, $context, $document);
            $results[] = $result;

            $this->eventDispatcher->dispatch(
                new CriterionEvaluatedEvent($result, $step->stepId, $document?->info->version ?? null),
            );

            if ($result->outcome !== CriterionOutcome::Passed) {
                break;
            }
        }

        return new CriterionResultCollection($results);
    }

    private function evaluateCriterion(
        SuccessCriterion $criterion,
        Step $step,
        WorkflowContextInterface $context,
        ?ArazzoDocument $document,
    ): CriterionResult {
        $type = $criterion->type ?? CriterionType::Simple;

        if ($this->criterionRegistry !== null) {
            $plugin = $this->criterionRegistry->resolve($criterion);

            if ($plugin !== null) {
                return $this->evaluatePlugin($criterion, $plugin, $step, $context, $document);
            }
        }

        try {
            $root = $this->resolveRoot($criterion, $type, $context, $step->stepId, $document);
        } catch (\Throwable $exception) {
            return self::error($criterion, CriterionReason::ContextUnresolved, $exception->getMessage());
        }

        try {
            $passed = $this->builtIn->evaluate($criterion, $type, $root, new EvaluationContext($context, $step->stepId, $document));
        } catch (ConditionSyntaxException $exception) {
            return self::error($criterion, CriterionReason::SyntaxError, $exception->getMessage());
        } catch (\Throwable $exception) {
            return self::error($criterion, CriterionReason::SelectorError, $exception->getMessage());
        }

        return self::result($criterion, $passed);
    }

    private function evaluatePlugin(
        SuccessCriterion $criterion,
        CriterionEvaluatorPluginInterface $plugin,
        Step $step,
        WorkflowContextInterface $context,
        ?ArazzoDocument $document,
    ): CriterionResult {
        try {
            $root = $this->criterionRoot($criterion, $context, $step->stepId, $document);
        } catch (\Throwable $exception) {
            return self::error($criterion, CriterionReason::ContextUnresolved, $exception->getMessage());
        }

        try {
            $passed = $plugin->evaluate($criterion, $root, $step, $context);
        } catch (\Throwable $exception) {
            return self::error($criterion, CriterionReason::PluginError, $exception->getMessage());
        }

        return self::result($criterion, $passed);
    }

    /**
     * Resolve the value a criterion evaluates against. Simple conditions resolve
     * their own operands, so they need no root.
     *
     * @throws \RuntimeException when the criterion declares no context but needs one
     */
    private function resolveRoot(
        SuccessCriterion $criterion,
        CriterionType $type,
        WorkflowContextInterface $context,
        string $stepId,
        ?ArazzoDocument $document,
    ): mixed {
        if ($type === CriterionType::Simple) {
            return null;
        }

        if ($type === CriterionType::Regex && $criterion->context === null) {
            throw new \RuntimeException('A regex criterion requires a context.');
        }

        return $this->criterionRoot($criterion, $context, $stepId, $document);
    }

    /**
     * The root a criterion applies to: its resolved `context` expression, or the
     * step's response body when no context is declared. Shared by the built-in
     * branch and the plugin dispatch so the two cannot drift.
     *
     * @throws \Throwable when the context expression cannot be evaluated or resolves to null
     */
    private function criterionRoot(
        SuccessCriterion $criterion,
        WorkflowContextInterface $context,
        string $stepId,
        ?ArazzoDocument $document,
    ): mixed {
        if ($criterion->context === null) {
            return $this->responseBody($context, $stepId);
        }

        $root = $this->expression->evaluate(
            new Expression($criterion->context),
            new EvaluationContext($context, $stepId, $document),
        );

        if ($root === null) {
            throw new \RuntimeException("Criterion context '{$criterion->context}' resolved to null.");
        }

        return $root;
    }

    private function responseBody(WorkflowContextInterface $context, string $stepId): mixed
    {
        $steps = $context->getSteps();
        $stepData = $steps[$stepId] ?? null;
        $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;

        return is_array($response) ? ($response['body'] ?? []) : [];
    }

    private function hasOperationTarget(Step $step): bool
    {
        return $step->target->operationId !== null || $step->target->operationPath !== null;
    }

    private static function isSuccessStatusCode(mixed $statusCode): bool
    {
        if (!is_int($statusCode) && !is_string($statusCode)) {
            // No status code recorded yet (e.g. mocked transports); fall back to pass.
            return true;
        }

        $code = (int) $statusCode;

        return $code >= 200 && $code < 300;
    }

    private static function result(SuccessCriterion $criterion, bool $passed): CriterionResult
    {
        return new CriterionResult(
            $criterion,
            $passed ? CriterionOutcome::Passed : CriterionOutcome::Failed,
            CriterionReason::Evaluated,
        );
    }

    private static function error(SuccessCriterion $criterion, CriterionReason $reason, string $message): CriterionResult
    {
        return new CriterionResult($criterion, CriterionOutcome::Error, $reason, $message);
    }
}
