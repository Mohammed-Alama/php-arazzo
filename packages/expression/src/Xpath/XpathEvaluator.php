<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Xpath;

use Alama\Arazzo\Contracts\Spec\Expression;

/**
 * XPath evaluation strategy; consumed by the ExpressionEngine facade and exposed
 * through its public queryXPath() and supportedXPathVersions() methods.
 */
interface XpathEvaluator
{
    /** @return list<string> Supported version tokens e.g. ['xpath-10']. */
    public function supportedVersions(): array;

    /**
     * Evaluate the XPath selector against the root value.
     *
     * @param  mixed  $rootValue  XML string or \DOMNode.
     * @param  string  $selector  XPath expression.
     * @param  string  $version  Requested version token e.g. 'xpath-10'.
     */
    public function query(mixed $rootValue, string $selector, string $version): mixed;
}
