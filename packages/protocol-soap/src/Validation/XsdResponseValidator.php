<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Soap\Validation;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Protocol\Soap\Execution\WsdlOperationResolver;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * ResponseValidatorInterface over the SOAP payload (plan F4.5): validates a
 * response's `view('xml')` DOM against the inline XSD schema the source WSDL
 * declares for the response element.
 *
 * The schema is picked from the resolved operation's
 * `responses['application/xml']['schema']` (the serialized inline
 * `<xsd:schema>`, F4.1). Resolution failure — a step that does not reference a
 * WSDL source, or a WSDL with no inline types — passes through (no schema is
 * "valid by default", same contract as the HTTP validator). A step-level
 * `schemaUrl` is not part of the Arazzo model, so only the resolved-operation
 * source is consulted.
 *
 * Because the inline schema declares the message element — never the SOAP
 * transport wrapper — validation runs against the envelope's first Body child
 * imported into a fresh document, not the whole `soapenv:Envelope`.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class XsdResponseValidator implements ResponseValidatorInterface
{
    public function __construct(
        private WsdlOperationResolver $operationResolver,
    ) {}

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $xsd = $this->findResponseSchema($step, $document);
        if ($xsd === null) {
            return;
        }

        if (!$decodedBody instanceof DOMDocument) {
            return;
        }

        $body = self::soapBodyDocument($decodedBody);
        if ($body === null) {
            return;
        }

        $violations = self::validateAgainstXsd($body, $xsd);

        if ($violations !== []) {
            throw new SchemaValidationException($step->stepId, $violations);
        }
    }

    private function findResponseSchema(Step $step, ?ArazzoDocument $document): ?string
    {
        if ($document === null) {
            return null;
        }

        try {
            $resolved = $this->operationResolver->resolve($step, $document);
        } catch (RuntimeException) {
            return null;
        }

        $bag = $resolved->normalized->responses['application/xml'] ?? null;
        if (!is_array($bag)) {
            return null;
        }

        $schema = $bag['schema'] ?? null;

        return is_string($schema) && $schema !== '' ? $schema : null;
    }

    private static function soapBodyDocument(DOMDocument $envelope): ?DOMDocument
    {
        $xpath = new DOMXPath($envelope);
        $nodes = $xpath->query('//*[local-name()="Body"]/*[1]');
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $element = $nodes->item(0);
        if (!$element instanceof DOMElement) {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($element, true));

        return $document;
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    private static function validateAgainstXsd(DOMDocument $document, string $xsd): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            // schemaValidateSource emits a PHP warning (bypassing libxml's
            // internal-error flag) when the schema itself is malformed; the
            // libxml errors collected below carry the detail we surface.
            $valid = @$document->schemaValidateSource($xsd);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($valid) {
            return [];
        }

        $violations = [];
        foreach ($errors as $error) {
            $violations[] = [
                'path' => 'line '.$error->line.', column '.$error->column,
                'message' => rtrim($error->message),
            ];
        }

        return $violations;
    }
}
