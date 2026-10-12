<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\NormalizedOpenApiOperation;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

/**
 * Port (D2): the SOAP sibling of OpenApiSourceNormalizer — turns a described
 * WSDL 1.1 source + raw content into a ResolvedOperation index for the core
 * registry. Ships in arazzo-sources (DF8/R2): spec reading is sources' domain,
 * so a WSDL parse never requires installing a protocol package; protocol-soap
 * only registers this class (F4.6).
 *
 * Index keys mirror OpenApiOperationResolver's accepted references:
 *   - plain operation name ("Add")
 *   - "$sourceDescriptions.<name>.<operationName>"
 *
 * The `NormalizedOpenApiOperation` carries the SOAP seams the protocol-soap
 * executor/validator consume (best-effort, DOM-only — no vendor):
 *   - resolvedServerUrl = <port location>, method = POST
 *   - headerParameters: Content-Type (+ SOAPAction per operation)
 *   - requestBodies['application/xml'] = { xsdElement, schema, portType, soapVersion, namespace }
 *   - responses['application/xml']   = { xsdElement, schema }
 * `schema` is the document's inline `<xsd:schema>` serialized for
 * `DOMDocument::schemaValidateSource`, or null when unresolvable.
 * `namespace` is the <definitions> targetNamespace the envelope/body elements
 * live in (consumed by protocol-soap's envelope builder).
 *
 * WSDL 2.0 (`<description>` root) is detected and rejected with an explicit
 * version exception rather than silently misparsed — the extraction vocabulary
 * in Phase F (service/port/binding/portType, soap:address, soap:operation) is
 * deliberately WSDL 1.1.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class WsdlSourceNormalizer implements SourceNormalizerInterface
{
    private const WSDL_NS = 'http://schemas.xmlsoap.org/wsdl/';

    private const SOAP_NS = 'http://schemas.xmlsoap.org/wsdl/soap/';

    private const SOAP12_NS = 'http://schemas.xmlsoap.org/wsdl/soap12/';

    private const WSDL2_NS = 'http://www.w3.org/ns/wsdl';

    private const XML_SCHEMA_NS = 'http://www.w3.org/2001/XMLSchema';

    private const MEDIA_TYPE_XML = 'application/xml';

    public function name(): string
    {
        return 'wsdl';
    }

    public function priority(): int
    {
        return 0;
    }

    public function supports(SourceType $type): bool
    {
        return $type === SourceType::Wsdl;
    }

    /**
     * @return array<string, ResolvedOperation>
     */
    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        $dom = $this->parse($rawContent);

        $root = $dom->documentElement;
        if ($root === null) {
            throw new InvalidArgumentException('Source content is not a WSDL document.');
        }

        if ($root->namespaceURI === self::WSDL2_NS && $root->localName === 'description') {
            throw UnsupportedSourceVersionException::forVersion('2.0', (string) $source->name);
        }

        if ($root->namespaceURI !== self::WSDL_NS || $root->localName !== 'definitions') {
            throw new InvalidArgumentException('Source content is not a WSDL 1.1 document.');
        }

        return $this->buildIndex($dom, $source);
    }

    private function parse(string $rawContent): DOMDocument
    {
        $dom = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $loaded = $dom->loadXML($rawContent, LIBXML_NONET);
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new InvalidArgumentException('Failed to parse source content as XML.');
        }

        return $dom;
    }

    /**
     * @return array<string, ResolvedOperation>
     */
    private function buildIndex(DOMDocument $dom, SourceDescription $source): array
    {
        $root = $dom->documentElement;
        if ($root === null) {
            return [];
        }

        $messages = $this->collectMessages($root);
        $portTypes = $this->collectPortTypes($root);
        $bindings = $this->collectBindings($root);
        $schemaXml = $this->extractInlineSchema($dom, $root);
        $targetNamespace = $root->getAttribute('targetNamespace');

        $index = [];
        foreach ($this->collectPorts($root) as $port) {
            $bindingName = $port['binding'];
            if ($bindingName === null || !isset($bindings[$bindingName])) {
                continue;
            }
            $binding = $bindings[$bindingName];
            $portTypeName = $binding['type'];
            if ($portTypeName === null || !isset($portTypes[$portTypeName])) {
                continue;
            }

            foreach ($portTypes[$portTypeName] as $operationName => $io) {
                $resolved = $this->resolveOperation($source, $operationName, [
                    'portType' => $portTypeName,
                    'location' => $port['location'],
                    'soapVersion' => $binding['soapVersion'],
                    'soapAction' => $binding['operations'][$operationName]['soapAction'] ?? null,
                    'namespace' => $targetNamespace === '' ? null : $targetNamespace,
                    'input' => $io['input'],
                    'output' => $io['output'],
                    'messages' => $messages,
                    'schemaXml' => $schemaXml,
                ]);

                $index[$operationName] = $resolved;
                $index['$sourceDescriptions.'.$source->name.'.'.$operationName] = $resolved;
            }
        }

        return $index;
    }

    /**
     * @return array<string, array{element: ?string}>
     */
    private function collectMessages(DOMElement $root): array
    {
        /** @var array<string, array{element: ?string}> $messages */
        $messages = [];
        foreach ($this->children($root, 'message') as $message) {
            $name = $message->getAttribute('name');
            if ($name === '') {
                continue;
            }
            $part = $this->firstChild($message, 'part');
            $element = $part?->getAttribute('element');
            $messages[$name] = ['element' => $element === '' ? null : $element];
        }

        return $messages;
    }

    /**
     * @return array<string, array<string, array{input: ?string, output: ?string}>>
     */
    private function collectPortTypes(DOMElement $root): array
    {
        /** @var array<string, array<string, array{input: ?string, output: ?string}>> $portTypes */
        $portTypes = [];
        foreach ($this->children($root, 'portType') as $portType) {
            $name = $portType->getAttribute('name');
            if ($name === '') {
                continue;
            }
            $operations = [];
            foreach ($this->children($portType, 'operation') as $operation) {
                $operationName = $operation->getAttribute('name');
                if ($operationName === '') {
                    continue;
                }
                $operations[$operationName] = [
                    'input' => $this->messageReference($operation, 'input'),
                    'output' => $this->messageReference($operation, 'output'),
                ];
            }
            $portTypes[$name] = $operations;
        }

        return $portTypes;
    }

    /**
     * @return array<string, array{type: ?string, soapVersion: string, operations: array<string, array{soapAction: ?string}>}>
     */
    private function collectBindings(DOMElement $root): array
    {
        /** @var array<string, array{type: ?string, soapVersion: string, operations: array<string, array{soapAction: ?string}>}> $bindings */
        $bindings = [];
        foreach ($this->children($root, 'binding') as $binding) {
            $name = $binding->getAttribute('name');
            if ($name === '') {
                continue;
            }
            $soapBinding = $this->firstChildIn($binding, 'binding', [self::SOAP_NS, self::SOAP12_NS]);
            $soapVersion = $soapBinding !== null && $soapBinding->namespaceURI === self::SOAP12_NS ? '1.2' : '1.1';

            $type = $this->localName($binding->getAttribute('type'));

            $bindings[$name] = [
                'type' => $type === '' ? null : $type,
                'soapVersion' => $soapVersion,
                'operations' => $this->collectBindingOperations($binding),
            ];
        }

        return $bindings;
    }

    /**
     * @return array<string, array{soapAction: ?string}>
     */
    private function collectBindingOperations(DOMElement $binding): array
    {
        /** @var array<string, array{soapAction: ?string}> $operations */
        $operations = [];
        foreach ($this->children($binding, 'operation') as $operation) {
            $operationName = $operation->getAttribute('name');
            if ($operationName === '') {
                continue;
            }
            $soapOperation = $this->firstChildIn($operation, 'operation', [self::SOAP_NS, self::SOAP12_NS]);
            $soapAction = $soapOperation?->getAttribute('soapAction');
            $operations[$operationName] = ['soapAction' => $soapAction === '' || $soapAction === null ? null : $soapAction];
        }

        return $operations;
    }

    /**
     * @return array<string, array{location: string, binding: ?string}>
     */
    private function collectPorts(DOMElement $root): array
    {
        /** @var array<string, array{location: string, binding: ?string}> $ports */
        $ports = [];
        foreach ($this->children($root, 'service') as $service) {
            foreach ($this->children($service, 'port') as $port) {
                $name = $port->getAttribute('name');
                if ($name === '') {
                    continue;
                }
                $address = $this->firstChildIn($port, 'address', [self::SOAP_NS, self::SOAP12_NS]);
                $location = $address?->getAttribute('location');
                if ($location === null || $location === '') {
                    continue;
                }
                $binding = $this->localName($port->getAttribute('binding'));

                $ports[$name] = [
                    'location' => $location,
                    'binding' => $binding === '' ? null : $binding,
                ];
            }
        }

        return $ports;
    }

    private function extractInlineSchema(DOMDocument $dom, DOMElement $root): ?string
    {
        $types = $this->firstChild($root, 'types');
        if ($types === null) {
            return null;
        }

        $schema = $this->firstChildIn($types, 'schema', [self::XML_SCHEMA_NS]);
        if ($schema === null) {
            return null;
        }

        $xml = $dom->saveXML($schema);

        return $xml === false ? null : $xml;
    }

    private function messageReference(DOMElement $operation, string $localName): ?string
    {
        $node = $this->firstChild($operation, $localName);
        $reference = $node?->getAttribute('message');

        if ($reference === null || $reference === '') {
            return null;
        }

        return $this->localName($reference);
    }

    /**
     * @param  array{
     *     portType: string,
     *     location: string,
     *     soapVersion: string,
     *     soapAction: ?string,
     *     namespace: ?string,
     *     input: ?string,
     *     output: ?string,
     *     messages: array<string, array{element: ?string}>,
     *     schemaXml: ?string,
     * }  $context
     */
    private function resolveOperation(SourceDescription $source, string $operationName, array $context): ResolvedOperation
    {
        $inputElement = null;
        if ($context['input'] !== null && isset($context['messages'][$context['input']])) {
            $inputElement = $context['messages'][$context['input']]['element'];
        }

        $outputElement = null;
        if ($context['output'] !== null && isset($context['messages'][$context['output']])) {
            $outputElement = $context['messages'][$context['output']]['element'];
        }

        $headerParameters = ['Content-Type' => ['value' => 'text/xml; charset=utf-8']];
        if ($context['soapAction'] !== null) {
            $headerParameters['SOAPAction'] = ['value' => $context['soapAction']];
        }

        $normalized = new NormalizedOpenApiOperation(
            path: $operationName,
            method: 'POST',
            resolvedServerUrl: $context['location'],
            pathParameters: [],
            queryParameters: [],
            headerParameters: $headerParameters,
            cookieParameters: [],
            requestBodies: [
                self::MEDIA_TYPE_XML => [
                    'xsdElement' => $inputElement,
                    'schema' => $context['schemaXml'],
                    'portType' => $context['portType'],
                    'soapVersion' => $context['soapVersion'],
                    'namespace' => $context['namespace'],
                ],
            ],
            responses: [
                self::MEDIA_TYPE_XML => [
                    'xsdElement' => $outputElement,
                    'schema' => $context['schemaXml'],
                ],
            ],
        );

        return new ResolvedOperation(
            source: $source,
            normalized: $normalized,
            operationName: $operationName,
        );
    }

    /**
     * Direct child elements of $parent whose local name matches, regardless
     * of namespace prefix.
     *
     * @return list<DOMElement>
     */
    private function children(DOMNode $parent, string $localName): array
    {
        $found = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $found[] = $child;
            }
        }

        return $found;
    }

    private function firstChild(DOMNode $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $namespaces
     */
    private function firstChildIn(DOMNode $parent, string $localName, array $namespaces): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement
                && $child->localName === $localName
                && in_array($child->namespaceURI, $namespaces, true)
            ) {
                return $child;
            }
        }

        return null;
    }

    private function localName(string $qname): string
    {
        $pos = strpos($qname, ':');

        return $pos === false ? $qname : substr($qname, $pos + 1);
    }
}
