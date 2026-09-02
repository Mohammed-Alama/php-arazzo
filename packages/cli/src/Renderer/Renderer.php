<?php

declare(strict_types=1);

namespace Alama\Arazzo\Renderer;

use Alama\Arazzo\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;

/**
 * Renders Arazzo documents as Mermaid flowcharts and Markdown docs.
 * Pure presentation: no parsing, no execution.
 */
final class Renderer
{
    public function toMermaid(ArazzoDocument $document, ?string $workflowId = null): string
    {
        $lines = ['flowchart TD'];
        $seq = 0;

        foreach ($this->workflows($document, $workflowId) as $workflow) {
            // First pass: assign a node id per step so goto/retry edges can
            // reference steps that have not been emitted yet.
            $nodes = [];

            foreach ($workflow->steps as $step) {
                $nodes[$step->stepId] = 'n'.(++$seq);
            }

            $lines[] = '';
            $lines[] = sprintf('  subgraph %s["workflow: %s"]', $this->id($workflow->workflowId), $this->esc($workflow->workflowId));

            $previous = null;

            foreach ($workflow->steps as $step) {
                $node = $nodes[$step->stepId];
                $label = $step->operationId ?? $step->operationPath ?? ($step->workflowId !== null ? '→ '.$step->workflowId : '?');

                $lines[] = sprintf('    %s["%s<br/>%s"]', $node, $this->esc($step->stepId), $this->esc((string) $label));

                if ($previous !== null) {
                    $lines[] = sprintf('    %s --> %s', $previous, $node);
                }

                foreach ($this->edges($step, $nodes) as [$style, $target]) {
                    $lines[] = sprintf('    %s %s %s', $node, $style, $target);
                }

                if ($this->endsSuccessfully($step)) {
                    $lines[] = sprintf('    %s --> done%s(["✔ end"])', $node, ++$seq);
                    $previous = null;

                    continue;
                }

                $previous = $node;
            }

            if ($previous !== null) {
                $lines[] = sprintf('    %s --> done%s(["✔ end"])', $previous, ++$seq);
            }

            $lines[] = '  end';
        }

        return implode("\n", $lines)."\n";
    }

    public function toMarkdown(ArazzoDocument $document): string
    {
        $out = '# '.$document->info->title."\n\n";

        if (($document->info->description ?? null) !== null) {
            $out .= $document->info->description."\n\n";
        }

        $out .= "- arazzo: **{$document->arazzo}**\n";
        $out .= '- version: **'.$document->info->version."**\n\n";

        if ($document->sourceDescriptions !== []) {
            $out .= "## Sources\n\n| Name | Type | URL |\n|---|---|---|\n";

            foreach ($document->sourceDescriptions as $source) {
                $out .= sprintf("| %s | %s | %s |\n", $source->name, $source->type->value, $source->url);
            }

            $out .= "\n";
        }

        foreach ($document->workflows as $workflow) {
            $out .= "## Workflow `{$workflow->workflowId}`\n\n";

            if (($workflow->summary ?? null) !== null) {
                $out .= "_{$workflow->summary}_\n\n";
            }

            if (($workflow->description ?? null) !== null) {
                $out .= $workflow->description."\n\n";
            }

            if ($workflow->steps === []) {
                $out .= "_no steps_\n\n";

                continue;
            }

            $out .= "| # | Step | Target | Criteria | Outputs |\n|---:|---|---|---|---|\n";

            foreach ($workflow->steps as $i => $step) {
                $criteria = [];

                foreach ($step->successCriteria as $criterion) {
                    $criteria[] = '`'.$criterion->condition.'`';
                }

                $outputs = [];

                foreach ($step->outputs as $name => $expression) {
                    $raw = is_object($expression) && property_exists($expression, 'raw')
                        ? $expression->raw
                        : (string) json_encode($expression);

                    $outputs[] = "`{$name}` = `{$raw}`";
                }

                $out .= sprintf(
                    "| %d | `%s` | %s | %s | %s |\n",
                    $i + 1,
                    $step->stepId,
                    $this->markdownTarget($step),
                    implode('<br/>', $criteria),
                    implode('<br/>', $outputs),
                );
            }

            if ($workflow->outputs !== []) {
                $out .= "\n**Workflow outputs:**\n\n";

                foreach ($workflow->outputs as $name => $expression) {
                    $raw = is_object($expression) && property_exists($expression, 'raw')
                        ? $expression->raw
                        : (string) json_encode($expression);

                    $out .= "- `{$name}` = `{$raw}`\n";
                }
            }

            $out .= "\n";
        }

        return $out;
    }

    /**
     * Mermaid edges for a step's success/failure routing.
     *
     * @return list<array{0:string,1:string}>
     */
    /**
     * @param  array<string,string>  $nodes
     * @return list<array{0:string,1:string}>
     */
    private function edges(Step $step, array $nodes): array
    {
        $edges = [];

        $push = function (bool $failure, string $label, string $targetStepId) use (&$edges, $nodes): void {
            $arrow = $failure ? '-.->|' : '-->|';
            $text = ($failure ? '✘ ' : '✔ ').$label;

            // Unknown targets (e.g. goto into another workflow) get a stub
            // node so the chart stays valid Mermaid.
            $edges[] = [$arrow.$text.'|', $nodes[$targetStepId] ?? ('id_'.$this->id($targetStepId))];
        };

        foreach ([$step->onSuccess, $step->onFailure] as $actions) {
            foreach ($actions as $action) {
                if ($action instanceof Reusable) {
                    continue;
                }

                if ($action instanceof SuccessGotoAction || $action instanceof FailureGotoAction) {
                    $push($action instanceof FailureGotoAction, 'goto', $action->stepId ?? $action->workflowId ?? '?');

                    continue;
                }

                if ($action instanceof RetryAction) {
                    $push(true, 'retry', $step->stepId);
                }
            }
        }

        return $edges;
    }

    private function endsSuccessfully(Step $step): bool
    {
        foreach ($step->onSuccess as $action) {
            if ($action instanceof SuccessEndAction) {
                return true;
            }
        }

        return false;
    }

    private function markdownTarget(Step $step): string
    {
        if ($step->workflowId !== null) {
            return "`→ {$step->workflowId}`";
        }

        if (($op = $step->operationPath ?? $step->operationId) !== null) {
            return "`{$op}`";
        }

        return $step->action !== null ? "`[{$step->action}]`" : '—';
    }

    /** @return list<Workflow> */
    private function workflows(ArazzoDocument $document, ?string $workflowId): array
    {
        if ($workflowId === null) {
            return $document->workflows;
        }

        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return [$workflow];
            }
        }

        return [];
    }

    private function esc(string $value): string
    {
        return str_replace(['"', "\n"], ["'", ' '], $value);
    }

    private function id(string $raw): string
    {
        return 'id_'.preg_replace('/[^A-Za-z0-9_]/', '_', $raw);
    }
}
