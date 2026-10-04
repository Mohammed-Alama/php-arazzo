<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Expression\Xpath\XpathEvaluator;

/**
 * @internal Package-internal dependency factory. Not for external use.
 * Used by ExpressionEngine to construct and cache internal collaborators.
 */
final class ExpressionDependencyProvider
{
    private ?XpathEvaluator $xpathEvaluator = null;

    public function getExpressionEngine(): ExpressionEngineInterface
    {
        return new ExpressionEngine($this);
    }

    public function getExpressionEvaluator(): ExpressionEvaluator
    {
        return new ExpressionEvaluator($this->getJsonPointer());
    }

    public function getSelectorEvaluator(): SelectorEvaluator
    {
        return new SelectorEvaluator($this->getXpathEvaluator(), $this->getExpressionEvaluator());
    }

    public function getJsonPathEvaluator(): JsonPathEvaluator
    {
        return new JsonPathEvaluator();
    }

    public function getXpathEvaluator(): XpathEvaluator
    {
        return $this->xpathEvaluator ??= new DomXpathEvaluator();
    }

    public function getParser(): Parser
    {
        return new Parser();
    }

    public function getJsonPointer(): JsonPointer
    {
        return new JsonPointer();
    }

    /** @internal For testing only */
    public function setXpathEvaluator(XpathEvaluator $xpathEvaluator): self
    {
        $this->xpathEvaluator = $xpathEvaluator;

        return $this;
    }
}
