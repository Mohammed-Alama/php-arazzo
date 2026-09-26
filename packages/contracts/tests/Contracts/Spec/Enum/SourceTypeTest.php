<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec\Enum;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

it('exposes the six standard source types with their wire values', function (): void {
    expect(array_column(SourceType::cases(), 'value'))->toBe([
        'openapi',
        'arazzo',
        'asyncapi',
        'wsdl',
        'protobuf',
        'graphql',
    ]);
});

it('backs each protocol reference source type with its enum value', function (): void {
    expect(SourceType::Wsdl->value)->toBe('wsdl');
    expect(SourceType::Protobuf->value)->toBe('protobuf');
    expect(SourceType::Graphql->value)->toBe('graphql');
});
