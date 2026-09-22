<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\ResponseTransferInterface;
use Alama\Arazzo\Contracts\Spec\ResponseTransfer;

it('carries status, headers and the raw protocol body', function (): void {
    $transfer = new ResponseTransfer(status: 200, headers: ['Content-Type' => 'text/xml'], rawBody: '<x/>');

    expect($transfer->status())->toBe(200)
        ->and($transfer->headers())->toBe(['Content-Type' => 'text/xml'])
        ->and($transfer->rawBody())->toBe('<x/>');
});

it('exposes views and the meta bag; absent views return null', function (): void {
    $transfer = new ResponseTransfer(status: 0, headers: [], rawBody: null, views: ['json' => ['ok' => true]]);

    expect($transfer->hasView('json'))->toBeTrue()
        ->and($transfer->view('json'))->toBe(['ok' => true])
        ->and($transfer->hasView('xml'))->toBeFalse()
        ->and($transfer->view('xml'))->toBeNull();
});

it('implements the contract seam', function (): void {
    $transfer = new ResponseTransfer(status: 0, headers: [], rawBody: null);

    expect($transfer)->toBeInstanceOf(ResponseTransferInterface::class)
        ->and($transfer->meta())->toBe([]);
});
