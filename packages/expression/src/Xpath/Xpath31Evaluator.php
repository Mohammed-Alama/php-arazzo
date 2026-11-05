<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Xpath;

use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;

/**
 * XPath 3.1-capable evaluator.
 *
 * XPath 1.0 core is a subset of XPath 3.1 (3.1 is backwards-compatible for
 * basic path expressions, predicates, and the core function library), so this
 * adapter delegates to {@see DomXpathEvaluator} while advertising both
 * `xpath-10` and `xpath-31` as supported versions.
 *
 * Full XPath 3.1 (maps, arrays, FLWOR expressions, higher-order functions)
 * is not implemented in pure PHP — that requires a Saxon/C or similar
 * adapter, which can be swapped in here without changing the plugin wiring
 * (see #297 follow-up).
 *
 * @internal stays out of the advertised contract; consumed by SelectorEvaluator via XpathSelectorPlugin.
 */
final class Xpath31Evaluator implements XpathEvaluator
{
    private DomXpathEvaluator $dom;

    public function __construct(?DomXpathEvaluator $dom = null)
    {
        $this->dom = $dom ?? new DomXpathEvaluator();
    }

    /** @return list<string> Supported version tokens: ['xpath-10', 'xpath-31']. */
    public function supportedVersions(): array
    {
        return ['xpath-10', 'xpath-31'];
    }

    /**
     * Evaluate the XPath selector against the root value.
     *
     * @param  mixed  $rootValue  XML string or \DOMNode.
     * @param  string  $selector  XPath expression.
     * @param  string  $version  Requested version token e.g. 'xpath-10' or 'xpath-31'.
     * @param  array<string, string>|null  $namespaces  XML namespace prefix => URI map.
     */
    public function query(mixed $rootValue, string $selector, string $version, ?array $namespaces = null): mixed
    {
        if (!in_array($version, $this->supportedVersions(), true)) {
            throw SelectorEvaluationException::unsupportedXpathVersion($version, $this->supportedVersions());
        }

        // XPath 1.0 core is a subset of XPath 3.1; basic path expressions,
        // predicates, and functions evaluate identically under DOMXPath.
        return $this->dom->query($rootValue, $selector, 'xpath-10', $namespaces);
    }
}
