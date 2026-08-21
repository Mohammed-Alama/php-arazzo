<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation\Xpath;

use Alama\Arazzo\Runner\Evaluation\Exceptions\SelectorEvaluationException;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;

final class DomXpathEvaluator implements XpathEvaluator
{
    public function query(mixed $rootValue, string $selector, string $version): mixed
    {
        if (!in_array($version, $this->supportedVersions(), true)) {
            throw SelectorEvaluationException::unsupportedXpathVersion($version, $this->supportedVersions());
        }

        $doc = $this->coerceDocument($rootValue);
        $xp = new DOMXPath($doc);

        $result = $xp->evaluate($selector);

        if ($result instanceof DOMNodeList) {
            if ($result->length === 0) {
                return null;
            }
            if ($result->length === 1) {
                $node = $result->item(0);

                return $node?->nodeValue;
            }

            $out = [];
            foreach ($result as $node) {
                $out[] = $node->nodeValue;
            }

            return $out;
        }

        return $result;
    }

    public function supportedVersions(): array
    {
        return ['xpath-10'];
    }

    private function coerceDocument(mixed $rootValue): DOMDocument
    {
        if ($rootValue instanceof DOMDocument) {
            return $rootValue;
        }

        if ($rootValue instanceof DOMNode && $rootValue->ownerDocument !== null) {
            return $rootValue->ownerDocument;
        }

        if (!is_string($rootValue)) {
            throw SelectorEvaluationException::xpathRequiresXml('/');
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$doc->loadXML($rootValue)) {
                throw SelectorEvaluationException::xpathRequiresXml('/');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $doc;
    }
}
