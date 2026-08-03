<?php

declare(strict_types=1);

namespace Alama\Arazzo\Exceptions;

final class SelectorEvaluationException extends ArazzoException
{
    /** @param list<string> $supported */
    public static function unsupportedXpathVersion(string $requested, array $supported): self
    {
        $list = implode(', ', $supported);

        return new self(
            "Requested XPath version '{$requested}' not supported by bound XpathEvaluator (supports: {$list}). Bind a custom XpathEvaluator to enable it.",
            '/',
            'selector.unsupported_xpath_version',
        );
    }

    public static function xpathRequiresXml(string $pointer): self
    {
        return new self(
            'XPath selector requires XML string or DOMNode input; got non-XML value.',
            $pointer,
            'selector.xpath_requires_xml',
        );
    }
}
