<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Dto;

use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\RawDocument;

it('holds raw data, path and format', function (): void {
    $doc = new RawDocument(['arazzo' => '1.0.0'], '/tmp/foo.yaml', Format::Yaml);

    expect($doc->data)->toBe(['arazzo' => '1.0.0'])
        ->and($doc->path)->toBe('/tmp/foo.yaml')
        ->and($doc->format)->toBe(Format::Yaml);
});

it('maps extensions to format', function (): void {
    expect(Format::fromExtension('yaml'))->toBe(Format::Yaml)
        ->and(Format::fromExtension('yml'))->toBe(Format::Yaml)
        ->and(Format::fromExtension('json'))->toBe(Format::Json)
        ->and(Format::fromExtension('txt'))->toBeNull();
});
