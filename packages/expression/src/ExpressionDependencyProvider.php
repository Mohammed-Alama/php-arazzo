<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginInterface;
use Alama\Arazzo\Expression\Plugins\JsonPathSelectorPlugin;
use Alama\Arazzo\Expression\Plugins\JsonPointerSelectorPlugin;
use Alama\Arazzo\Expression\Plugins\XpathSelectorPlugin;
use Alama\Arazzo\Expression\Registries\SelectorPluginRegistry;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Expression\Xpath\XpathEvaluator;

/**
 * @internal Package-internal dependency factory. Not for external use.
 * Used by ExpressionEngine to construct and cache internal collaborators.
 */
final class ExpressionDependencyProvider
{
    private ?XpathEvaluator $xpathEvaluator = null;

    private ?SelectorPluginRegistry $selectorPluginRegistry = null;

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
        return new SelectorEvaluator($this->getSelectorPluginRegistry(), $this->getExpressionEvaluator());
    }

    public function getJsonPathEvaluator(): JsonPathEvaluator
    {
        return new JsonPathEvaluator();
    }

    public function getXpathEvaluator(): XpathEvaluator
    {
        return $this->xpathEvaluator ??= new DomXpathEvaluator();
    }

    public function getSelectorPluginRegistry(): SelectorPluginRegistry
    {
        if ($this->selectorPluginRegistry === null) {
            $registry = new SelectorPluginRegistry();
            $registry->register(ExpressionType::JsonPath, new JsonPathSelectorPlugin($this->getJsonPathEvaluator()));
            $registry->register(ExpressionType::JsonPointer, new JsonPointerSelectorPlugin());
            $registry->register(ExpressionType::XPath, new XpathSelectorPlugin($this->getXpathEvaluator()));

            $this->selectorPluginRegistry = $registry;
        }

        return $this->selectorPluginRegistry;
    }

    public function getParser(): Parser
    {
        return new Parser();
    }

    public function getJsonPointer(): JsonPointer
    {
        return new JsonPointer();
    }

    public function getInterpolator(): Interpolator
    {
        return new Interpolator();
    }

    /** @internal For testing only */
    public function setXpathEvaluator(XpathEvaluator $xpathEvaluator): self
    {
        $this->xpathEvaluator = $xpathEvaluator;
        $this->selectorPluginRegistry = null;

        return $this;
    }

    /** @internal For testing only */
    public function registerSelectorPlugin(ExpressionType $type, SelectorPluginInterface $plugin, int $priority = 0): self
    {
        $this->getSelectorPluginRegistry()->register($type, $plugin, $priority);

        return $this;
    }
}
