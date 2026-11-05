<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Plugins;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginInterface;
use Alama\Arazzo\Expression\JsonPointer;

/**
 * JSON Pointer selector plugin. Delegates to {@see JsonPointer}.
 *
 * @internal stays out of the advertised contract; not part of the public API surface.
 */
final class JsonPointerSelectorPlugin implements SelectorPluginInterface
{
    public function supports(ExpressionType $type): bool
    {
        return $type === ExpressionType::JsonPointer;
    }

    /**
     * @param  mixed  $rootValue  array-shaped document
     * @param  array<string, string>|null  $namespaces  ignored for JSON Pointer
     */
    public function evaluate(mixed $rootValue, string $expression, ?string $version, ?array $namespaces = null): mixed
    {
        if (!is_array($rootValue)) {
            return null;
        }

        return JsonPointer::resolve($rootValue, $expression);
    }

    public function supportedVersions(): array
    {
        return [];
    }
}
