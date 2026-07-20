<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution;

use Alama\LaravelArazzo\Resolution\AsyncApiResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

it('extracts the whole document for an empty pointer', function (): void {
    $source = new AsyncApiResolvedSource(['asyncapi' => '2.6.0', 'channels' => ['x' => []]]);

    expect($source->extract(''))->toBe(['asyncapi' => '2.6.0', 'channels' => ['x' => []]]);
});

it('extracts a nested value by json pointer', function (): void {
    $source = new AsyncApiResolvedSource(['channels' => ['rides/created' => ['subscribe' => ['operationId' => 'onRideCreated']]]]);

    expect($source->extract('/channels/rides~1created/subscribe/operationId'))->toBe('onRideCreated');
});

it('throws for an unresolvable pointer', function (): void {
    $source = new AsyncApiResolvedSource(['channels' => []]);

    expect(fn () => $source->extract('/channels/missing'))->toThrow(UnresolvableReferenceException::class);
});
