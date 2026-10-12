<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Transfer;

use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransferMapper;
use DOMDocument;
use GuzzleHttp\Psr7\Response;

it('maps a successful SOAP response to an xml view without fault or error meta', function (): void {
    $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
        .'<soapenv:Body><ns:AddResponse xmlns:ns="urn:calculator"><ns:result>5</ns:result></ns:AddResponse></soapenv:Body>'
        .'</soapenv:Envelope>';
    $response = new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], $body);

    $transfer = (new SoapResponseTransferMapper())->map($response);

    expect($transfer->status())->toBe(200);
    expect($transfer->hasView('xml'))->toBeTrue();
    expect($transfer->view('xml'))->toBeInstanceOf(DOMDocument::class);
    expect($transfer->meta())->toBe([]);
    expect($transfer->headers()['Content-Type'])->toBe('text/xml; charset=utf-8');
    expect($transfer->rawBody())->toBe($body);
});

it('maps a SOAP 1.1 fault envelope into soap.faultcode and soap.faultstring meta', function (): void {
    $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
        .'<soapenv:Body><soapenv:Fault><faultcode>soapenv:Client</faultcode>'
        .'<faultstring>Invalid argument a</faultstring></soapenv:Fault></soapenv:Body></soapenv:Envelope>';

    $transfer = (new SoapResponseTransferMapper())->map(new Response(200, [], $body));

    expect($transfer->hasView('xml'))->toBeTrue();
    expect($transfer->meta()['soap.faultcode'])->toBe('soapenv:Client');
    expect($transfer->meta()['soap.faultstring'])->toBe('Invalid argument a');
    expect($transfer->meta())->not->toHaveKey('error');
});

it('maps a SOAP 1.2 fault envelope (Code/Value + Reason/Text)', function (): void {
    $body = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">'
        .'<soap:Body><soap:Fault><soap:Code><soap:Value>soap:Sender</soap:Value></soap:Code>'
        .'<soap:Reason><soap:Text xml:lang="en">Quantity must be positive</soap:Text></soap:Reason></soap:Fault>'
        .'</soap:Body></soap:Envelope>';

    $transfer = (new SoapResponseTransferMapper())->map(new Response(200, [], $body));

    expect($transfer->meta()['soap.faultcode'])->toBe('soap:Sender');
    expect($transfer->meta()['soap.faultstring'])->toBe('Quantity must be positive');
});

it('flags unparsable bodies with a meta error and no xml view', function (): void {
    $transfer = (new SoapResponseTransferMapper())->map(new Response(200, [], 'this is not xml at all'));

    expect($transfer->hasView('xml'))->toBeFalse();
    expect($transfer->meta()['error'])->not->toBeNull();
    expect($transfer->rawBody())->toBe('this is not xml at all');
});

it('flags non-2xx responses with the raw status and a meta error', function (): void {
    $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
        .'<soapenv:Body><soapenv:Fault><faultcode>soapenv:Server</faultcode>'
        .'<faultstring>Backend exploded</faultstring></soapenv:Fault></soapenv:Body></soapenv:Envelope>';

    $transfer = (new SoapResponseTransferMapper())->map(new Response(500, [], $body));

    expect($transfer->status())->toBe(500);
    expect($transfer->meta()['error'])->not->toBeNull();
    expect($transfer->hasView('xml'))->toBeFalse();
});
