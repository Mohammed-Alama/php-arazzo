<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Resolver;

use Alama\Arazzo\Contracts\Interfaces\ReplacementTargetResolverInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;

/**
 * ReplacementTargetResolverInterface over DOM (plan F4.4): applies a payload
 * replacement value to the nodes an `xpath:` (or bare) expression matches
 * inside a SOAP envelope/response DOM instead of a JSON path.
 *
 * Container contract: a DOMDocument, or a ResponseTransferInterface exposing
 * an 'xml' view (the SoapResponseTransfer). Matched element/attribute/text
 * nodes have their content replaced (booleans use the XML lexical form); the
 * return value is the count of touched nodes — or null when the container is
 * unsupported or the expression is invalid, so a dispatcher can chain.
 *
 * The verified contracts interface resolve() is a WRITER
 * (`resolve(container, target, value)`); the plan text's getter sketch does
 * not match it, and the interface is normative. `supports('xpath')` is the
 * type gate; an optional `xpath:` prefix on the target is stripped
 * defensively so both payload-replacement spellings work.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class XPathReplacementTargetResolver implements ReplacementTargetResolverInterface
{
    private const TARGET_PREFIX = 'xpath:';

    public function name(): string
    {
        return 'xpath-replacement';
    }

    public function priority(): int
    {
        return 200;
    }

    public function supports(string $targetType): bool
    {
        return $targetType === 'xpath';
    }

    public function resolve(mixed $container, string $target, mixed $value): mixed
    {
        $document = self::document($container);
        if ($document === null) {
            return null;
        }

        $expression = str_starts_with($target, self::TARGET_PREFIX) ? substr($target, strlen(self::TARGET_PREFIX)) : $target;
        $text = self::scalarText($value);

        $xpath = new DOMXPath($document);
        self::registerNamespaces($xpath);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $nodes = $xpath->query($expression);
        } finally {
            libxml_use_internal_errors($previous);
        }

        if ($nodes === false) {
            return null;
        }

        $touched = 0;
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $node->textContent = $text;
                $touched++;
            } elseif ($node instanceof DOMAttr || $node instanceof DOMText) {
                $node->nodeValue = $text;
                $touched++;
            }
        }

        return $touched;
    }

    private static function document(mixed $container): ?DOMDocument
    {
        if ($container instanceof DOMDocument) {
            return $container;
        }

        if ($container instanceof ResponseTransferInterface && $container->hasView('xml')) {
            $view = $container->view('xml');

            return $view instanceof DOMDocument ? $view : null;
        }

        return null;
    }

    private static function scalarText(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function registerNamespaces(DOMXPath $xpath): void
    {
        $nodes = $xpath->query('//namespace::*');
        if ($nodes === false) {
            return;
        }

        $registered = [];
        foreach ($nodes as $node) {
            $prefix = $node->localName;
            $uri = $node->nodeValue;
            if ($prefix === null || $prefix === '' || $prefix === 'xml' || isset($registered[$prefix]) || $uri === null || $uri === '') {
                continue;
            }

            $registered[$prefix] = true;
            $xpath->registerNamespace($prefix, $uri);
        }
    }
}
