<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Plugins;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginInterface;
use Alama\Arazzo\Expression\JsonPathEvaluator;

/**
 * JSONPath selector plugin. Delegates to {@see JsonPathEvaluator}.
 *
 * @internal stays out of the advertised contract; not part of the public API surface.
 */
final class JsonPathSelectorPlugin implements SelectorPluginInterface
{
    public function __construct(private JsonPathEvaluator $evaluator = new JsonPathEvaluator()) {}

    public function supports(ExpressionType $type): bool
    {
        return $type === ExpressionType::JsonPath;
    }

    /**
     * @param  mixed  $rootValue  array or object
     */
    public function evaluate(mixed $rootValue, string $expression, ?string $version): mixed
    {
        if (!is_array($rootValue) && !is_object($rootValue)) {
            return null;
        }

        return $this->evaluator->evaluate($expression, $rootValue);
    }

    public function supportedVersions(): array
    {
        return [];
    }
}
