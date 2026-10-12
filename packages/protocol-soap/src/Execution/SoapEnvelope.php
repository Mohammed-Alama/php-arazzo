<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Execution;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * DOM-based SOAP envelope builder (plan F4.2).
 *
 * Produces a SOAP 1.1/1.2 envelope whose body carries the operation element
 * (`<tns:{operationName}>` in the WSDL target namespace) with the step's
 * resolved parameters serialised as child elements: booleans use the XML
 * lexical form (true/false), nested arrays become nested elements, nulls
 * become empty elements, and an optional correlation id becomes a Header
 * child while the whole envelope round-trips through DOM.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SoapEnvelope
{
    private const SOAP11_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    private const SOAP12_NS = 'http://www.w3.org/2003/05/soap-envelope';

    private const PREFIX = 'soapenv';

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function build(
        string $operationName,
        string $namespace,
        array $parameters,
        ?string $correlationId = null,
        string $soapVersion = '1.1',
    ): self {
        $soapNamespace = $soapVersion === '1.2' ? self::SOAP12_NS : self::SOAP11_NS;
        $dom = new DOMDocument('1.0', 'UTF-8');

        $envelope = self::element($dom, $soapNamespace, self::PREFIX.':Envelope');
        $dom->appendChild($envelope);

        if ($correlationId !== null) {
            $header = self::element($dom, $soapNamespace, self::PREFIX.':Header');
            $identifier = $dom->createElement('CorrelationId');
            $identifier->textContent = $correlationId;
            $header->appendChild($identifier);
            $envelope->appendChild($header);
        }

        $body = self::element($dom, $soapNamespace, self::PREFIX.':Body');
        $envelope->appendChild($body);

        $operation = self::element($dom, $namespace, 'tns:'.$operationName);
        $body->appendChild($operation);

        foreach ($parameters as $name => $value) {
            self::appendParameter($dom, $operation, (string) $name, $value);
        }

        return new self($dom);
    }

    private function __construct(private readonly DOMDocument $dom) {}

    public function __toString(): string
    {
        $xml = $this->dom->saveXML();

        return $xml === false ? '' : $xml;
    }

    private static function appendParameter(DOMDocument $dom, DOMElement $parent, string $name, mixed $value): void
    {
        $element = $dom->createElement($name);
        $parent->appendChild($element);

        if (is_array($value)) {
            foreach ($value as $childName => $childValue) {
                self::appendParameter($dom, $element, (string) $childName, $childValue);
            }

            return;
        }

        if ($value === null) {
            return;
        }

        $element->textContent = self::textFor($value);
    }

    private static function textFor(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function element(DOMDocument $dom, string $namespace, string $qualifiedName): DOMElement
    {
        $element = $dom->createElementNS($namespace, $qualifiedName);

        if ($element === false) {
            throw new RuntimeException('Failed to create SOAP envelope element.');
        }

        return $element;
    }
}
