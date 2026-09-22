<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Registries;

use Alama\Arazzo\Contracts\Interfaces\ExpressionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Evaluation\Plugins\JsonPathExpressionPlugin;

/**
 * Registry for expression evaluator plugins.
 *
 * Plugins are stored with an integer priority (higher = earlier). The registry
 * returns the first plugin whose {@see ExpressionEvaluatorPluginInterface::supports()}
 * returns true.
 */
final class ExpressionEvaluatorRegistry
{
    /** @var array<int, ExpressionEvaluatorPluginInterface> */
    private array $plugins = [];

    public function __construct()
    {
        // Built‑in default plugin (lowest priority)
        $this->register(new JsonPathExpressionPlugin(), 0);
    }

    public function register(ExpressionEvaluatorPluginInterface $plugin, int $priority = 0): void
    {
        $this->plugins[$priority][] = $plugin;
        krsort($this->plugins); // higher priority first
    }

    /**
     * @return ExpressionEvaluatorPluginInterface|null
     */
    public function resolve(Expression $expression): ?ExpressionEvaluatorPluginInterface
    {
        foreach ($this->plugins as $priorityPlugins) {
            foreach ($priorityPlugins as $plugin) {
                if ($plugin->supports($expression)) {
                    return $plugin;
                }
            }
        }
        return null;
    }

    /** @return ExpressionEvaluatorPluginInterface[] */
    public function all(): array
    {
        $flat = [];
        foreach ($this->plugins as $priorityPlugins) {
            $flat = array_merge($flat, $priorityPlugins);
        }
        return $flat;
    }
}