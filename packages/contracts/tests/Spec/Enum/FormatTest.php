<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Dto\Enum;

use Alama\Arazzo\Spec\Enum\Format;

it('resolves yaml/yml/json extensions case-insensitively', function (): void {
    expect(Format::fromExtension('YAML'))->toBe(Format::Yaml)
        ->and(Format::fromExtension('yml'))->toBe(Format::Yaml)
        ->and(Format::fromExtension('json'))->toBe(Format::Json)
        ->and(Format::fromExtension('xml'))->toBeNull();
});
