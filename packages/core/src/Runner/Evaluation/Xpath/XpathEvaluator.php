<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Xpath;

interface XpathEvaluator
{
    /** @return list<string> Supported version tokens e.g. ['xpath-10']. */
    public function supportedVersions(): array;

    /**
     * Evaluate the XPath selector against the root value.
     *
     * @param mixed $rootValue XML string or \DOMNode.
     * @param string $selector XPath expression.
     * @param string $version Requested version token e.g. 'xpath-10'.
     */
    public function query(mixed $rootValue, string $selector, string $version): mixed;
}
