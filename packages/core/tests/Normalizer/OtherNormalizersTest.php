<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\Swagger2Normalizer;
use Alama\Arazzo\Support\Exceptions\NotImplementedException;

it('swagger 2 normalizer throws', function (): void {
    $normalizer = new Swagger2Normalizer();

    $normalizer->normalize([], '/test', 'get');
})->throws(NotImplementedException::class, 'Swagger 2.0 normalization is not yet implemented.');

it('open api 31 normalizer normalizes like 30', function (): void {
    // 3.1 documents are structurally identical for everything this
    // pipeline consumes; the normalizer is a documented passthrough.
    $normalizer = new OpenApi31Normalizer();
    $normalized = $normalizer->normalize([
        'servers' => [['url' => 'https://api.test']],
        'paths' => [
            '/test' => [
                'get' => [
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ],
    ], '/test', 'get');

    expect($normalized->path)->toBe('/test')
        ->and($normalized->method)->toBe('get')
        ->and($normalized->resolvedServerUrl)->toBe('https://api.test');
});
