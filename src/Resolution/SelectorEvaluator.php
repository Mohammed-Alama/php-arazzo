<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Dto\Enum\ExpressionType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Selector;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\JsonPathEvaluator;
use Alama\LaravelArazzo\Execution\JsonPointer;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Resolution\Xpath\XpathEvaluator;

class SelectorEvaluator
{
    public function __construct(
        private XpathEvaluator $xpath,
        private ExpressionEvaluator $expressions,
    ) {
    }

    public function evaluate(Selector $sel, WorkflowContext $wf, string $stepId): mixed
    {
        $root = $sel->context !== null
            ? $this->expressions->evaluate(new Expression($sel->context), $wf, $stepId)
            : $wf->rootScope();

        return match ($sel->type) {
            ExpressionType::JsonPath => is_array($root) || is_object($root)
                ? JsonPathEvaluator::evaluate($sel->selector, $root)
                : null,
            ExpressionType::JsonPointer => is_array($root)
                ? JsonPointer::resolve($root, $sel->selector)
                : null,
            ExpressionType::XPath => $this->xpath->query(
                $root,
                $sel->selector,
                $sel->version ?? 'xpath-10',
            ),
        };
    }
}
