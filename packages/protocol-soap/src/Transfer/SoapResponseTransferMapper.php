<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Transfer;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps a raw PSR-7 SOAP response into a `SoapResponseTransfer`.
 *
 * Rules (documented homes, consumed by the SOAP executor):
 *   - 2xx + parsable XML envelope → view('xml') set; SOAP 1.1/1.2 fault
 *     envelopes additionally set meta['soap.faultcode'] / ['soap.faultstring'].
 *   - non-2xx or unparsable body → raw status preserved, meta['error'] set.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SoapResponseTransferMapper
{
    private const SOAP11_FAULT_CODE = '//*[local-name()=\'Fault\']';

    public function map(ResponseInterface $response): SoapResponseTransfer
    {
        $status = $response->getStatusCode();
        $headers = $this->flattenHeaders($response->getHeaders());
        $rawBody = (string) $response->getBody();

        $dom = $this->parseXml($rawBody);
        if ($dom !== null && $status >= 200 && $status < 300) {
            $fault = $this->faultKeys($dom);
            $meta = [];
            if ($fault['faultcode'] !== null) {
                $meta['soap.faultcode'] = $fault['faultcode'];
                $meta['soap.faultstring'] = $fault['faultstring'];
            }

            return new SoapResponseTransfer(
                status: $status,
                headers: $headers,
                rawBody: $rawBody,
                views: ['xml' => $dom],
                meta: $meta,
            );
        }

        return new SoapResponseTransfer(
            status: $status,
            headers: $headers,
            rawBody: $rawBody,
            views: [],
            meta: ['error' => 'Unparsable or non-2xx SOAP response.'],
        );
    }

    private function parseXml(string $rawBody): ?DOMDocument
    {
        if ($rawBody === '') {
            return null;
        }

        $dom = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $loaded = $dom->loadXML($rawBody, LIBXML_NONET);
        } finally {
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $dom : null;
    }

    /**
     * @return array{faultcode: ?string, faultstring: ?string}
     */
    private function faultKeys(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $fault = $this->firstElement($xpath, self::SOAP11_FAULT_CODE);
        if ($fault === null) {
            return ['faultcode' => null, 'faultstring' => null];
        }

        // SOAP 1.1: <faultcode>/<faultstring>; SOAP 1.2: <Code><Value> + <Reason><Text>.
        $code = $this->firstText($xpath, './*[local-name()="faultcode"]', $fault);
        $string = $this->firstText($xpath, './*[local-name()="faultstring"]', $fault);

        if ($code === null) {
            $code = $this->firstText($xpath, './/*[local-name()="Value"]', $fault);
        }
        if ($string === null) {
            $string = $this->firstText($xpath, './/*[local-name()="Text"]', $fault);
        }

        return ['faultcode' => $code, 'faultstring' => $string];
    }

    private function firstElement(DOMXPath $xpath, string $expression, ?DOMElement $context = null): ?DOMElement
    {
        $nodes = $xpath->query($expression, $context);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function firstText(DOMXPath $xpath, string $expression, DOMElement $context): ?string
    {
        $element = $this->firstElement($xpath, $expression, $context);

        return $element?->textContent;
    }

    /**
     * @param  array<array-key, array<string>>  $headers
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        $flattened = [];
        foreach ($headers as $name => $values) {
            $first = ($values[array_key_first($values)] ?? '');

            $flattened[(string) $name] = (string) $first;
        }

        return $flattened;
    }
}
