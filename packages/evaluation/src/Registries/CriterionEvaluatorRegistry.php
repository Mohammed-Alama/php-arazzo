<?php

declare(strict_types=1);

namespace Alama\Arazzo\Evaluation\Registries;

use Alama\Arazzo\Contracts\Interfaces\CriterionEvaluatorPluginInterface;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Evaluation\Plugins\JsonPathCriterionPlugin;

/**
 * Registry for criterion evaluator plugins.
 *
 * Plugins are stored with an integer priority (higher = earlier). The registry
 * returns the first plugin whose {@see CriterionEvaluatorPluginInterface::supports()}
 * returns true.
 */
final class CriterionEvaluatorRegistry
{
    /** @var array<int, CriterionEvaluatorPluginInterface> */
    private array $plugins = [];

    public function __construct()
    {
        // Built‑in default plugin (lowest priority)
        $this->register(new JsonPathCriterionPlugin(), 0);
    }

    public function register(CriterionEvaluatorPluginInterface $plugin, int $priority = 0): void
    {
        $this->plugins[$priority][] = $plugin;
        krsort($this->plugins);
    }

    public function resolve(CriterionType|SuccessCriterion $criterion): ?CriterionEvaluatorPluginInterface
    {
        foreach ($this->plugins as $priorityPlugins) {
            foreach ($priorityPlugins as $plugin) {
                if ($plugin->supports($criterion)) {
                    return $plugin;
                }
            }
        }

        return null;
    }

    /** @return CriterionEvaluatorPluginInterface[] */
    public function all(): array
    {
        $flat = [];
        foreach ($this->plugins as $priorityPlugins) {
            $flat = array_merge($flat, $priorityPlugins);
        }

        return $flat;
    }
}
