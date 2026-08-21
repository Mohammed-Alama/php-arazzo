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

    public function test_open_api31_normalizer_throws(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('OpenAPI 3.1 normalization is not yet implemented.');

        $normalizer = new OpenApi31Normalizer();
        $normalizer->normalize([], '/test', 'get');
    }
}
