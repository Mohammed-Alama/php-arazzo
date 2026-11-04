<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;

/**
 * Read-only side of a selector-plugin registry.
 *
 * Consumers outside `packages/expression` type-hint this interface so they
 * never depend on the concrete `SelectorPluginRegistry` (which stays
 * package-internal to `packages/expression`).
 */
interface SelectorPluginRegistryInterface
{
    /**
     * Whether a plugin is registered for the given expression type.
     */
    public function has(ExpressionType $type): bool;

    /**
     * The highest-priority plugin registered for the given type, or null.
     */
    public function get(ExpressionType $type): ?SelectorPluginInterface;

    /**
     * @return list<string> Supported version tokens for the given type, or [] if no plugin is registered.
     */
    public function supportedVersions(ExpressionType $type): array;
}
