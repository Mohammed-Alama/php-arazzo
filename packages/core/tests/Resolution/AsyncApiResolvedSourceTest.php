<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Resolution\AsyncApiResolvedSource;
use Alama\Arazzo\Resolution\Exceptions\UnresolvableReferenceException;

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

it('throws for an invalid json pointer format', function (): void {
    $source = new AsyncApiResolvedSource(['channels' => []]);

    expect(fn () => $source->extract('channels/missing'))->toThrow(UnresolvableReferenceException::class);
});

it('extracts a nested value from a numeric array by json pointer', function (): void {
    $source = new AsyncApiResolvedSource(['channels' => ['item0', 'item1']]);

    expect($source->extract('/channels/1'))->toBe('item1');
});
