<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * First-supports()-wins registry for operation executor plugins.
 *
 * Registration order does not matter: plugins are sorted by priority at
 * resolution time. Lower priority values are tried first.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class OperationExecutorRegistry
{
    /** @var list<OperationExecutorPluginInterface> */
    private array $plugins = [];

    public function register(OperationExecutorPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    public function resolve(Step $step, ArazzoDocument $document): ?OperationExecutorPluginInterface
    {
        $sorted = $this->plugins;
        usort($sorted, static fn (OperationExecutorPluginInterface $a, OperationExecutorPluginInterface $b): int => $a->priority() <=> $b->priority());

        foreach ($sorted as $plugin) {
            if ($plugin->supports($step, $document)) {
                return $plugin;
            }
        }

        return null;
    }

    /** @return list<OperationExecutorPluginInterface> */
    public function all(): array
    {
        return $this->plugins;
    }
}
