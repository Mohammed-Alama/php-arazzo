<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Registries;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginInterface;

/**
 * Registry for selector plugins.
 *
 * Plugins are keyed by {@see ExpressionType} and stored with an integer
 * priority (higher = earlier). The registry returns the highest-priority
 * plugin registered for a given type.
 *
 * @internal stays out of the advertised contract; consumed by SelectorEvaluator.
 */
final class SelectorPluginRegistry
{
    /** @var array<string, array<int, list<SelectorPluginInterface>>> */
    private array $plugins = [];

    public function register(ExpressionType $type, SelectorPluginInterface $plugin, int $priority = 0): void
    {
        $this->plugins[$type->value][$priority][] = $plugin;
        krsort($this->plugins[$type->value]);
    }

    public function get(ExpressionType $type): ?SelectorPluginInterface
    {
        $plugins = $this->plugins[$type->value] ?? [];

        foreach ($plugins as $priorityPlugins) {
            foreach ($priorityPlugins as $plugin) {
                return $plugin;
            }
        }

        return null;
    }

    public function has(ExpressionType $type): bool
    {
        return $this->get($type) !== null;
    }

    /**
     * @return list<string> Supported version tokens for the given type, or [] if no plugin is registered.
     */
    public function supportedVersions(ExpressionType $type): array
    {
        $plugin = $this->get($type);

        return $plugin !== null ? $plugin->supportedVersions() : [];
    }

    /** @return SelectorPluginInterface[] */
    public function all(): array
    {
        $flat = [];

        foreach ($this->plugins as $typePlugins) {
            foreach ($typePlugins as $priorityPlugins) {
                $flat = array_merge($flat, $priorityPlugins);
            }
        }

        return $flat;
    }
}
