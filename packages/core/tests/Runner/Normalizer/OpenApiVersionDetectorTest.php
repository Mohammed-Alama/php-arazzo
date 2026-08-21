<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Normalizer;

use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OpenApiVersionDetectorTest extends TestCase
{
    private OpenApiVersionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new OpenApiVersionDetector();
    }

    public function test_detects_swagger2(): void
    {
        $document = ['swagger' => '2.0'];
        $this->assertEquals('2.0', $this->detector->detect($document));
    }

    public function test_detects_open_api30(): void
    {
        $document = ['openapi' => '3.0.3'];
        $this->assertEquals('3.0', $this->detector->detect($document));
    }

    public function test_detects_open_api31(): void
    {
        $document = ['openapi' => '3.1.0'];
        $this->assertEquals('3.1', $this->detector->detect($document));
    }

    public function test_throws_on_unknown_version(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported or missing OpenAPI/Swagger version in document.');

        $document = ['openapi' => '4.0.0'];
        $this->detector->detect($document);
    }

    public function test_throws_on_missing_version(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported or missing OpenAPI/Swagger version in document.');

        $document = ['info' => ['title' => 'Test API']];
        $this->detector->detect($document);
    }
}
