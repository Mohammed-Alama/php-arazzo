<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Normalizer;

use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use InvalidArgumentException;

beforeEach(function (): void {
    $this->detector = new OpenApiVersionDetector();
});

it('detects swagger 2.0', function (): void {
    expect($this->detector->detect(['swagger' => '2.0']))->toBe('2.0');
});

it('detects open api 3.0', function (): void {
    expect($this->detector->detect(['openapi' => '3.0.3']))->toBe('3.0');
});

it('detects open api 3.1', function (): void {
    expect($this->detector->detect(['openapi' => '3.1.0']))->toBe('3.1');
});

it('throws on unknown version', function (): void {
    $this->detector->detect(['openapi' => '4.0.0']);
})->throws(InvalidArgumentException::class, 'Unsupported or missing OpenAPI/Swagger version in document.');

it('throws on missing version', function (): void {
    $this->detector->detect(['info' => ['title' => 'Test API']]);
})->throws(InvalidArgumentException::class, 'Unsupported or missing OpenAPI/Swagger version in document.');
