<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

use Alama\Arazzo\Dto\Enum\ExpressionType;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Resolver\Xpath\XpathEvaluator;
use Alama\Arazzo\Runner\ExpressionEvaluator;
use Alama\Arazzo\Runner\JsonPathEvaluator;
use Alama\Arazzo\Runner\JsonPointer;
use Alama\Arazzo\Runner\WorkflowContext;

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
