<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\Normalizer\WsdlSourceNormalizer;
use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;
use InvalidArgumentException;

function makeWsdlSourceNormalizer(): WsdlSourceNormalizer
{
    return new WsdlSourceNormalizer();
}

function wsdlFixture(string $filename): string
{
    $content = file_get_contents(__DIR__.'/../fixtures/'.$filename);
    if ($content === false) {
        throw new RuntimeException('Fixture not found: '.$filename);
    }

    return $content;
}

it('advertises WSDL support only for the wsdl source type', function (): void {
    $normalizer = makeWsdlSourceNormalizer();

    expect($normalizer->name())->toBe('wsdl');
    expect($normalizer->priority())->toBe(0);
    expect($normalizer->supports(SourceType::Wsdl))->toBeTrue();
    expect($normalizer->supports(SourceType::Openapi))->toBeFalse();
    expect($normalizer->supports(SourceType::Asyncapi))->toBeFalse();
    expect($normalizer->supports(SourceType::Graphql))->toBeFalse();
});

it('builds a ResolvedOperation index for a WSDL 1.1 document', function (): void {
    $normalizer = makeWsdlSourceNormalizer();
    $source = new SourceDescription(
        name: 'calculator',
        url: 'file://'.__DIR__.'/../fixtures/calculator.wsdl',
        type: SourceType::Wsdl,
    );

    $index = $normalizer->normalize($source, wsdlFixture('calculator.wsdl'));

    expect($index)->toHaveKeys(['Add', '$sourceDescriptions.calculator.Add']);

    $resolved = $index['Add'];
    expect($resolved)->toBeInstanceOf(ResolvedOperation::class);
    expect($resolved->sourceType())->toBe(SourceType::Wsdl);
    expect($resolved->binding())->toBe('soap');
    expect($resolved->operationName)->toBe('Add');

    $normalized = $resolved->normalized;
    expect($normalized->path)->toBe('Add');
    expect($normalized->method)->toBe('POST');
    expect($normalized->resolvedServerUrl)->toBe('http://localhost:8080/soap/calculator');
    expect($normalized->headerParameters['Content-Type']['value'])->toBe('text/xml; charset=utf-8');
    expect($normalized->headerParameters['SOAPAction']['value'])->toBe('urn:calculator:Add');

    $request = $normalized->requestBodies['application/xml'];
    expect($request['xsdElement'])->toBe('tns:Add');
    expect($request['portType'])->toBe('CalculatorSoap');
    expect($request['soapVersion'])->toBe('1.1');
    expect($request['namespace'])->toBe('urn:calculator');
    expect($request['schema'])->toContain('<xsd:element name="Add"');

    $response = $normalized->responses['application/xml'];
    expect($response['xsdElement'])->toBe('tns:AddResponse');
    expect($response['schema'])->toContain('<xsd:element name="AddResponse"');
});

it('derives a null schema when a WSDL documents no inline types', function (): void {
    $normalizer = makeWsdlSourceNormalizer();
    $source = new SourceDescription(name: 'schema-less', url: 'http://example.test/service.wsdl', type: SourceType::Wsdl);

    $wsdl = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <definitions xmlns="http://schemas.xmlsoap.org/wsdl/"
                 xmlns:tns="urn:schemeless"
                 xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
                 name="SchemaLess" targetNamespace="urn:schemeless">
        <message name="PingIn"><part name="parameters" element="tns:Ping"/></message>
        <message name="PingOut"><part name="parameters" element="tns:PingResponse"/></message>
        <portType name="PingPortType">
            <operation name="Ping">
                <input message="tns:PingIn"/>
                <output message="tns:PingOut"/>
            </operation>
        </portType>
        <binding name="PingBinding" type="tns:PingPortType">
            <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
            <operation name="Ping">
                <soap:operation soapAction="urn:schemeless:Ping" style="document"/>
                <input><soap:body use="literal"/></input>
                <output><soap:body use="literal"/></output>
            </operation>
        </binding>
        <service name="PingService">
            <port name="PingPort" binding="tns:PingBinding">
                <soap:address location="http://localhost:9999/ping"/>
            </port>
        </service>
    </definitions>
    XML;

    $index = $normalizer->normalize($source, $wsdl);
    expect($index['Ping']->normalized->requestBodies['application/xml']['schema'])->toBeNull();
    expect($index['Ping']->normalized->responses['application/xml']['schema'])->toBeNull();
});

it('indexes every operation with its own soapAction and endpoint', function (): void {
    $normalizer = makeWsdlSourceNormalizer();
    $source = new SourceDescription(
        name: 'orders',
        url: 'file://'.__DIR__.'/../fixtures/order-service.wsdl',
        type: SourceType::Wsdl,
    );

    $index = $normalizer->normalize($source, wsdlFixture('order-service.wsdl'));

    expect($index)->toHaveKeys(['PlaceOrder', 'GetOrder', '$sourceDescriptions.orders.PlaceOrder', '$sourceDescriptions.orders.GetOrder']);

    expect($index['PlaceOrder']->normalized->resolvedServerUrl)->toBe('https://orders.example.test/soap');
    expect($index['PlaceOrder']->normalized->headerParameters['SOAPAction']['value'])->toBe('urn:orders:PlaceOrder');
    expect($index['PlaceOrder']->normalized->requestBodies['application/xml']['xsdElement'])->toBe('tns:PlaceOrder');
    expect($index['PlaceOrder']->normalized->requestBodies['application/xml']['namespace'])->toBe('urn:orders');

    expect($index['GetOrder']->normalized->headerParameters['SOAPAction']['value'])->toBe('urn:orders:GetOrder');
    expect($index['GetOrder']->normalized->responses['application/xml']['xsdElement'])->toBe('tns:GetOrderResponse');
});

it('rejects non-XML source content', function (): void {
    $normalizer = makeWsdlSourceNormalizer();
    $source = new SourceDescription(name: 'json', url: 'http://example.test/api.json', type: SourceType::Wsdl);

    $normalizer->normalize($source, '{"openapi":"3.0.3"}');
})->throws(InvalidArgumentException::class);

it('rejects a WSDL 2.0 description document as unsupported', function (): void {
    $normalizer = makeWsdlSourceNormalizer();
    $source = new SourceDescription(name: 'v2', url: 'http://example.test/service2.wsdl', type: SourceType::Wsdl);

    $wsdl2 = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <description xmlns="http://www.w3.org/ns/wsdl"
                 xmlns:tns="urn:example"
                 targetNamespace="urn:example">
        <interface name="ExampleInterface">
            <operation name="Ping"/>
        </interface>
        <service name="ExampleService">
            <endpoint name="ExampleEndpoint" address="http://localhost/example" binding="tns:ExampleBinding"/>
        </service>
    </description>
    XML;

    $normalizer->normalize($source, $wsdl2);
})->throws(UnsupportedSourceVersionException::class);
