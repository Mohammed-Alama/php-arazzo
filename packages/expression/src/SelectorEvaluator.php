<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;
use Alama\Arazzo\Expression\Registries\SelectorPluginRegistry;

/**
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
final class SelectorEvaluator
{
    public function __construct(
        private SelectorPluginRegistry $plugins,
        private ExpressionEvaluator $expressions,
    ) {}

    public function evaluate(Selector $sel, WorkflowContextInterface $wf, string $stepId): mixed
    {
        // Spec default when context is omitted: the current step's response body.
        if ($sel->context !== null) {
            $root = $this->expressions->evaluate(
                new Expression($sel->context),
                new EvaluationContext($wf, $stepId),
            );
        } else {
            $steps = $wf->getSteps();
            $stepData = $steps[$stepId] ?? null;
            $response = is_array($stepData) ? ($stepData['response'] ?? null) : null;
            $body = is_array($response) ? ($response['body'] ?? []) : [];

            $root = is_array($body) ? $body : [];
        }

        $plugin = $this->plugins->get($sel->type);

        if ($plugin === null) {
            throw new SelectorEvaluationException(
                "No selector plugin registered for type '{$sel->type->value}'.",
                'workflows/'.($wf->getWorkflowId() ?? 'unknown').'/steps/'.$stepId,
                'selector.no_plugin',
            );
        }

        try {
            return $plugin->evaluate($root, $sel->selector, $sel->version);
        } catch (SelectorEvaluationException $e) {
            // Enrich capability errors with the document location.
            $location = 'workflows/'.($wf->getWorkflowId() ?? 'unknown').'/steps/'.$stepId;

            throw new SelectorEvaluationException($e->getMessage(), $location, $e->codeId, $e);
        }
    }
}
