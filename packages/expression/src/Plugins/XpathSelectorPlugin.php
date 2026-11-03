<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Plugins;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\Interfaces\SelectorPluginInterface;
use Alama\Arazzo\Expression\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Expression\Xpath\XpathEvaluator;

/**
 * XPath selector plugin. Delegates to a bound {@see XpathEvaluator}.
 *
 * The default {@see DomXpathEvaluator} implements
 * XPath 1.0 only and reports ['xpath-10'] as its supported version. A custom
 * XpathEvaluator (e.g. Saxon/C for XPath 3.1) can be bound to advertise
 * additional versions.
 *
 * @internal stays out of the advertised contract; not part of the public API surface.
 */
final class XpathSelectorPlugin implements SelectorPluginInterface
{
    public function __construct(private XpathEvaluator $evaluator) {}

    public function supports(ExpressionType $type): bool
    {
        return $type === ExpressionType::XPath;
    }

    /**
     * @param  mixed  $rootValue  XML string or \DOMNode
     */
    public function evaluate(mixed $rootValue, string $expression, ?string $version): mixed
    {
        return $this->evaluator->query($rootValue, $expression, $version ?? 'xpath-10');
    }

    public function supportedVersions(): array
    {
        return $this->evaluator->supportedVersions();
    }
}
