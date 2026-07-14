<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Support;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\SymbolTable;

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
                if ($p->value instanceof Expression) {
                    yield new ExpressionSite(
                        "/workflows/{$wi}/parameters/{$pi}/value", $p->value, $syms, null, 'wf.parameters',
                    );
                }
            }
            foreach ($wf->outputs as $name => $expr) {
                yield new ExpressionSite(
                    "/workflows/{$wi}/outputs/{$name}", $expr, $syms, null, 'wf.outputs',
                );
            }

            foreach ($wf->steps as $si => $s) {
                foreach ($s->parameters as $pi => $p) {
                    if ($p->value instanceof Expression) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/value", $p->value, $syms, $s->stepId, 'parameters',
                        );
                    }
                }
                if ($s->requestBody !== null) {
                    if ($s->requestBody->payload instanceof Expression) {
                        yield new ExpressionSite(
                            "/workflows/{$wi}/steps/{$si}/requestBody/payload", $s->requestBody->payload, $syms, $s->stepId, 'requestBody',
                        );
                    }
                    foreach ($s->requestBody->replacements as $ri => $r) {
                        if ($r->value instanceof Expression) {
                            yield new ExpressionSite(
                                "/workflows/{$wi}/steps/{$si}/requestBody/replacements/{$ri}/value", $r->value, $syms, $s->stepId, 'requestBody',
                            );
                        }
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
                    yield new ExpressionSite(
                        "/workflows/{$wi}/steps/{$si}/outputs/{$name}", $expr, $syms, $s->stepId, 'outputs',
                    );
                }
            }
        }
    }
}
