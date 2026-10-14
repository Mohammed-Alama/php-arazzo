<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Execution;

use Alama\Arazzo\Protocol\Soap\Execution\SoapFaultMapper;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransfer;
use DOMDocument;

it('maps a SOAP fault transfer into a failed outcome carrying the fault keys', function (): void {
    $dom = new DOMDocument();
    $transfer = new SoapResponseTransfer(
        status: 200,
        headers: ['Content-Type' => 'text/xml; charset=utf-8'],
        rawBody: '<soapenv:Fault/>',
        views: ['xml' => $dom],
        meta: ['soap.faultcode' => 'soapenv:Client', 'soap.faultstring' => 'addition requires finite values'],
    );

    $outcome = (new SoapFaultMapper())->map($transfer);

    expect($outcome->failureCategory)->toBe('soap-fault');
    expect($outcome->statusCode)->toBe(200);
    expect($outcome->suspended)->toBeFalse();
    expect($outcome->responseBody['transfer'])->toBe($transfer);
    expect($outcome->responseBody['soap.faultcode'])->toBe('soapenv:Client');
    expect($outcome->responseBody['soap.faultstring'])->toBe('addition requires finite values');
    expect($outcome->outputs)->toBe([]);
});

it('honours the failure category supplied for transport errors', function (): void {
    $transfer = new SoapResponseTransfer(status: 502, headers: [], rawBody: 'boom', meta: ['error' => 'upstream unavailable']);

    $outcome = (new SoapFaultMapper())->map($transfer, 'transport');

    expect($outcome->failureCategory)->toBe('transport');
    expect($outcome->statusCode)->toBe(502);
    expect($outcome->suspended)->toBeFalse();
    expect($outcome->responseBody['transfer'])->toBe($transfer);
});

it('keeps the response body to the transfer when the fault keys are absent', function (): void {
    $transfer = new SoapResponseTransfer(status: 200, headers: [], rawBody: '<ok/>');

    $outcome = (new SoapFaultMapper())->map($transfer, 'transport');

    expect($outcome->responseBody)->toBe(['transfer' => $transfer]);
});
