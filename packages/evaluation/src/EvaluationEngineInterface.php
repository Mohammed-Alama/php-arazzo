<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;

/**
 * Entry-point seam for the runtime evaluation package.
 *
 * Downstream packages depend on this interface. It exposes dynamic evaluation:
 * evaluating expressions against state, criteria evaluation, selectors,
 * string interpolation, payload replacement, JSONPath, JSON Pointer, and XPath queries.
 */
interface EvaluationEngineInterface
{
    /**
     * Evaluate an Arazzo expression against a run context.
     */
    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed;

    /**
     * Evaluate a list of success criteria against the current workflow step.
     *
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Evaluate a step's declared success criteria (2xx default when applicable).
     */
    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Evaluate a selector (JSONPath, JSON-pointer or XPath) against the workflow context.
     */
    public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed;

    /**
     * Query an XPath selector against an XML string or DOM node.
     */
    public function queryXPath(mixed $rootValue, string $selector, string $version): mixed;

    /**
     * @return list<string> Supported XPath version tokens, e.g. ['xpath-10'].
     */
    public function supportedXPathVersions(): array;

    /**
     * Interpolate {$...} expression references within a string against the workflow context.
     */
    public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string;

    /**
     * Apply a step's payload replacements to an array-shaped body.
     *
     * @param  array<array-key, mixed>  $body
     * @param  callable(PayloadReplacement): mixed|null  $resolveValue
     * @return array<array-key, mixed>
     */
    public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array;

    /**
     * Evaluate a JSONPath expression against data.
     *
     * @param  array<array-key, mixed>|object  $data
     */
    public function jsonPath(string $expression, array|object $data): mixed;

    /**
     * Resolve a JSON Pointer against an array-shaped document.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function jsonPointer(array $data, ?string $pointer): mixed;
}
