<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\RequestPipeline\Data\InjectionResult;
use GuzzleHttp\Psr7\Request;

it('carries only the request when no key was injected', function (): void {
    $request = new Request('POST', 'https://api.example.com/charges');
    $result = new InjectionResult($request);

    expect($result->request)->toBe($request)
        ->and($result->key)->toBeNull()
        ->and($result->header)->toBeNull();
});

it('carries the key and header name when a key was injected', function (): void {
    $request = new Request('POST', 'https://api.example.com/charges');
    $result = new InjectionResult($request, 'abc123', 'Idempotency-Key');

    expect($result->request)->toBe($request)
        ->and($result->key)->toBe('abc123')
        ->and($result->header)->toBe('Idempotency-Key');
});

it('exposes the request with the idempotency header applied', function (): void {
    $result = new InjectionResult(
        (new Request('POST', 'https://api.example.com/charges'))->withHeader('Idempotency-Key', 'abc123'),
        'abc123',
        'Idempotency-Key',
    );

    expect($result->request->getHeaderLine('Idempotency-Key'))->toBe('abc123');
});
