<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Soap\Transfer;

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;
use Alama\Arazzo\Protocol\Soap\Transfer\SoapResponseTransfer;
use DOMDocument;

it('exposes the response seam over constructor state', function (): void {
    $transfer = new SoapResponseTransfer(
        status: 200,
        headers: ['Content-Type' => 'text/xml; charset=utf-8'],
        rawBody: '<?xml version="1.0"?><Envelope/>',
        views: ['xml' => new DOMDocument()],
        meta: ['soap.faultcode' => 'soapenv:Client'],
    );

    expect($transfer)->toBeInstanceOf(ResponseTransferInterface::class);
    expect($transfer->status())->toBe(200);
    expect($transfer->headers())->toBe(['Content-Type' => 'text/xml; charset=utf-8']);
    expect($transfer->rawBody())->toBe('<?xml version="1.0"?><Envelope/>');
    expect($transfer->hasView('xml'))->toBeTrue();
    expect($transfer->view('xml'))->toBeInstanceOf(DOMDocument::class);
    expect($transfer->view('json'))->toBeNull();
    expect($transfer->meta())->toBe(['soap.faultcode' => 'soapenv:Client']);
});
