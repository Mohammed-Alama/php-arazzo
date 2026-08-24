<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Support;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Expression\WorkflowSymbols;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Selector;

final class ExpressionWalker
{
    /**
     * @return iterable<ExpressionSite>
     */
    public function walk(ArazzoDocument $doc, SymbolTable $symbols): iterable
    {
        foreach ($doc->workflows as $wi => $wf) {
            $syms = $symbols->workflows[$wf->workflowId] ?? null;

            foreach ($wf->parameters as $pi => $p) {
                if ($p instanceof Reusable) {
                    continue;
                }

                yield from $this->extract($p->value, "/workflows/{$wi}/parameters/{$pi}/value", $syms, null, 'wf.parameters');
            }
            foreach ($wf->outputs as $name => $expr) {
                yield from $this->extract($expr, "/workflows/{$wi}/outputs/{$name}", $syms, null, 'wf.outputs');
            }

            foreach ($wf->steps as $si => $s) {
                foreach ($s->parameters as $pi => $p) {
                    if ($p instanceof Reusable) {
                        continue;
                    }

                    yield from $this->extract($p->value, "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/value", $syms, $s->stepId, 'parameters');
                }
                if ($s->requestBody !== null) {
                    yield from $this->extract($s->requestBody->payload, "/workflows/{$wi}/steps/{$si}/requestBody/payload", $syms, $s->stepId, 'requestBody');
                    foreach ($s->requestBody->replacements as $ri => $r) {
                        yield from $this->extract($r->value, "/workflows/{$wi}/steps/{$si}/requestBody/replacements/{$ri}/value", $syms, $s->stepId, 'requestBody');
                    }
                }
                foreach ($s->successCriteria as $ci => $c) {
                    if ($c->context !== null && str_starts_with($c->context, '{$')) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/successCriteria/{$ci}/context", new Expression($c->context), $syms, $s->stepId, 'criteria',
                        );
                    }
                    if (str_starts_with($c->condition, '{$')) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/successCriteria/{$ci}/condition", new Expression($c->condition), $syms, $s->stepId, 'criteria',
                        );
                    }
                }
                foreach ($s->outputs as $name => $expr) {
                    yield from $this->extract($expr, "/workflows/{$wi}/steps/{$si}/outputs/{$name}", $syms, $s->stepId, 'outputs');
                }
            }
        }
    }

    /**
     * @param 'components'|'criteria'|'onFailure'|'onSuccess'|'outputs'|'parameters'|'requestBody'|'wf.outputs'|'wf.parameters' $context
     *
     * @return iterable<ExpressionSite>
     */
    private function extract(mixed $value, string $pointer, ?WorkflowSymbols $syms, ?string $stepId, string $context): iterable
    {
        if ($value instanceof Expression) {
            yield new ExpressionSite($pointer, $value, $syms, $stepId, $context);
        } elseif ($value instanceof Selector) {
            if ($value->context !== null && str_starts_with($value->context, '$')) {
                yield new ExpressionSite("{$pointer}/context", new Expression('{' . $value->context . '}'), $syms, $stepId, $context);
            }
        }
    }
}
