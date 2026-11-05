<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\Registries\SelectorPluginRegistry;

/**
 * Plugin contract for evaluating a selector type against a resolved root value.
 *
 * Plugins are registered in {@see SelectorPluginRegistry}
 * keyed by {@see ExpressionType}. The engine is type-agnostic: it looks up the
 * plugin for a selector's type and delegates evaluation to it.
 *
 * @internal stays out of the advertised contract; consumed by SelectorEvaluator.
 */
interface SelectorPluginInterface
{
    /**
     * Whether this plugin handles the given expression type.
     */
    public function supports(ExpressionType $type): bool;

    /**
     * Evaluate the selector expression against a resolved root value.
     *
     * @param  mixed  $rootValue  Resolved root (array, object, XML string, DOMNode, …)
     * @param  string  $expression  Selector expression (XPath, JSONPath, JSON Pointer, …)
     * @param  string|null  $version  Version token, may be null; plugin decides the default
     * @param  array<string, string>|null  $namespaces  XML namespace prefix => URI map, for XPath selectors
     */
    public function evaluate(mixed $rootValue, string $expression, ?string $version, ?array $namespaces = null): mixed;

    /**
     * @return list<string> Supported version tokens, e.g. ['xpath-10']. Empty for unversioned types.
     */
    public function supportedVersions(): array;
}
