<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Data\EvaluationInput;
use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;
use Alama\Arazzo\Expression\Xpath\XpathEvaluator;
use Alama\Arazzo\Spec\Enum\ExpressionType;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Spec\Selector;

class SelectorEvaluator
{
    public function __construct(
        private XpathEvaluator $xpath,
        private ExpressionEvaluator $expressions,
    ) {}

    public function evaluate(Selector $sel, WorkflowContextInterface $wf, string $stepId): mixed
    {
        // Spec default when context is omitted: the current step's response body.
        if ($sel->context !== null) {
            $root = $this->expressions->evaluate(new Expression($sel->context), new EvaluationInput($wf, $stepId));
        } else {
            $steps = $wf->getSteps();
            $stepData = $steps[$stepId] ?? null;
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
            $body = is_array($response) ? ($response['body'] ?? []) : [];

            $root = is_array($body) ? $body : [];
        }

        return match ($sel->type) {
            ExpressionType::JsonPath => is_array($root) || is_object($root)
                ? JsonPathEvaluator::evaluate($sel->selector, $root)
                : null,
            ExpressionType::JsonPointer => is_array($root)
                ? JsonPointer::resolve($root, $sel->selector)
                : null,
            ExpressionType::XPath => (function () use ($root, $sel, $wf, $stepId) {
                try {
                    return $this->xpath->query(
                        $root,
                        $sel->selector,
                        $sel->version ?? 'xpath-10',
                    );
                } catch (SelectorEvaluationException $e) {
                    // Enrich capability errors with the document location.
                    $location = 'workflows/'.($wf->getWorkflowId() ?? 'unknown').'/steps/'.$stepId;

                    throw new SelectorEvaluationException($e->getMessage(), $location, $e->codeId, $e);
                }
            })(),
        };
    }
}
