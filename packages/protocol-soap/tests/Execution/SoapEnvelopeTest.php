<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Execution;

use Alama\Arazzo\Protocol\Soap\Execution\SoapEnvelope;
use DOMDocument;

it('builds a SOAP 1.1 envelope carrying the operation element and scalar parameters', function (): void {
    $xml = (string) SoapEnvelope::build(
        operationName: 'Add',
        namespace: 'urn:calculator',
        parameters: ['a' => 5, 'b' => 3],
    );

    expect($xml)->toContain('soapenv:Envelope');
    expect($xml)->toContain('xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"');
    expect($xml)->toContain('<soapenv:Body>');
    expect($xml)->toContain('<tns:Add xmlns:tns="urn:calculator">');
    expect($xml)->toContain('<a>5</a>');
    expect($xml)->toContain('<b>3</b>');
    expect($xml)->toContain('</soapenv:Envelope>');
});

it('serialises booleans in XML lexical form instead of PHP cast form', function (): void {
    $xml = (string) SoapEnvelope::build('Flip', 'urn:flip', ['on' => true, 'off' => false]);

    expect($xml)->toContain('<on>true</on>');
    expect($xml)->toContain('<off>false</off>');
});

it('serialises nested arrays as nested elements', function (): void {
    $xml = (string) SoapEnvelope::build('Order', 'urn:orders', [
        'customer' => ['name' => 'Ada', 'vip' => true],
    ]);

    expect($xml)->toContain('<customer>');
    expect($xml)->toContain('<name>Ada</name>');
    expect($xml)->toContain('<vip>true</vip>');
});

it('emits a correlation element in the header when given', function (): void {
    $xml = (string) SoapEnvelope::build('Add', 'urn:calculator', ['a' => 1], correlationId: 'exec-123');

    expect($xml)->toContain('<soapenv:Header>');
    expect($xml)->toContain('<CorrelationId>exec-123</CorrelationId>');
});

it('switches to the SOAP 1.2 envelope namespace on request', function (): void {
    $xml = (string) SoapEnvelope::build('Add', 'urn:calculator', ['a' => 1], soapVersion: '1.2');

    expect($xml)->toContain('xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope"');
    expect($xml)->not->toContain('schemas.xmlsoap.org/soap/envelope');
});

it('round-trips through DOMDocument::loadXML as well-formed XML', function (): void {
    $xml = (string) SoapEnvelope::build('Add', 'urn:calculator', ['a' => 1], correlationId: 'exec-123');

    $dom = new DOMDocument();
    expect($dom->loadXML($xml))->toBeTrue();
});
