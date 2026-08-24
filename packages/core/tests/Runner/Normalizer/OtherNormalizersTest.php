<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Normalizer;

use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\Swagger2Normalizer;
use Alama\Arazzo\Support\Exceptions\NotImplementedException;
use PHPUnit\Framework\TestCase;

class OtherNormalizersTest extends TestCase
{
    public function test_swagger2_normalizer_throws(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('Swagger 2.0 normalization is not yet implemented.');

        $normalizer = new Swagger2Normalizer();
        $normalizer->normalize([], '/test', 'get');
    }

    public function test_open_api31_normalizer_normalizes_like_30(): void
    {
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
    }
}
