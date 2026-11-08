<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginRegistryInterface;
use Alama\Arazzo\Expression\Internal\EmbeddedExpressionSplitter;
use Alama\Arazzo\Expression\Plugins\JsonPathSelectorPlugin;
use Alama\Arazzo\Expression\Plugins\JsonPointerSelectorPlugin;
use Alama\Arazzo\Expression\Plugins\XpathSelectorPlugin;
use Alama\Arazzo\Expression\Registries\SelectorPluginRegistry;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Expression\Xpath\Xpath31Evaluator;
use Alama\Arazzo\Expression\Xpath\XpathEvaluator;

/**
 * @internal Package-internal dependency factory. Not for external use.
 * Used by ExpressionEngine to construct and cache internal collaborators.
 */
final class ExpressionDependencyProvider
{
    private ?SelectorPluginRegistryInterface $selectorPlugins = null;

    private ?XpathEvaluator $xpathEvaluator = null;

    private ?SelectorPluginRegistryInterface $selectorPluginRegistry = null;

    private ?EmbeddedExpressionSplitter $embeddedExpressionSplitter = null;

    private ?ExpressionEvaluator $expressionEvaluator = null;

    private ?SelectorEvaluator $selectorEvaluator = null;

    public function __construct(?SelectorPluginRegistryInterface $selectorPlugins = null)
    {
        $this->selectorPlugins = $selectorPlugins;
    }

    public function getExpressionEngine(): ExpressionEngineInterface
    {
        return new ExpressionEngine($this->selectorPlugins);
    }

    public function getExpressionEvaluator(): ExpressionEvaluator
    {
        // Held per provider (and therefore per engine) so the bounded AST cache
        // survives across evaluations instead of being rebuilt on every call.
        return $this->expressionEvaluator ??= new ExpressionEvaluator($this->getJsonPointer());
    }

    public function getSelectorEvaluator(): SelectorEvaluator
    {
        return $this->selectorEvaluator ??= new SelectorEvaluator($this->getSelectorPluginRegistry(), $this->getExpressionEvaluator());
    }

    public function getJsonPathEvaluator(): JsonPathEvaluator
    {
        return new JsonPathEvaluator();
    }

    public function getXpathEvaluator(): XpathEvaluator
    {
        return $this->xpathEvaluator ??= new DomXpathEvaluator();
    }

    public function getSelectorPluginRegistry(): SelectorPluginRegistryInterface
    {
        if ($this->selectorPlugins !== null) {
            return $this->selectorPlugins;
        }

        if ($this->selectorPluginRegistry === null) {
            $registry = new SelectorPluginRegistry();
            $registry->register(ExpressionType::JsonPath, new JsonPathSelectorPlugin($this->getJsonPathEvaluator()));
            $registry->register(ExpressionType::JsonPointer, new JsonPointerSelectorPlugin());
            $registry->register(ExpressionType::XPath, new XpathSelectorPlugin(new Xpath31Evaluator()));

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

    public function getEmbeddedExpressionSplitter(): EmbeddedExpressionSplitter
    {
        return $this->embeddedExpressionSplitter ??= new EmbeddedExpressionSplitter($this->getParser());
    }
}
