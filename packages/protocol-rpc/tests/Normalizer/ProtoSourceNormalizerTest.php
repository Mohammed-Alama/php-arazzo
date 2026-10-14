<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoSourceNormalizer;
use InvalidArgumentException;

function protobufSource(string $name, string $url): SourceDescription
{
    return new SourceDescription($name, $url, SourceType::Protobuf);
}

it('supports only protobuf sources and reports a stable name and priority', function (): void {
    $normalizer = new ProtoSourceNormalizer();

    expect($normalizer->name())->toBe('protobuf');
    expect($normalizer->priority())->toBe(0);
    expect($normalizer->supports(SourceType::Protobuf))->toBeTrue();
    expect($normalizer->supports(SourceType::Wsdl))->toBeFalse();
    expect($normalizer->supports(SourceType::Openapi))->toBeFalse();
});

it('normalises a .proto source into an RPC operation index', function (): void {
    $normalizer = new ProtoSourceNormalizer();
    $source = protobufSource('calculator', 'https://example.test/calculator.proto');

    $index = $normalizer->normalize($source, calcProtoContent());

    $dotted = $index['$sourceDescriptions.calculator.CalculatorService/Add'] ?? null;
    expect($dotted)->toBeInstanceOf(ResolvedOperation::class);
    expect($dotted->normalized->path)->toBe('CalculatorService/Add');
    expect($dotted->normalized->method)->toBe('POST');
    expect($dotted->normalized->resolvedServerUrl)->toBe('https://example.test/calculator.proto');

    $plain = $index['CalculatorService/Add'] ?? null;
    expect($plain)->toBe($dotted);

    $bags = $dotted->normalized->requestBodies['application/protobuf'] ?? null;
    expect($bags)->toBeArray();
    expect($bags['package'])->toBe('demo.calculator');
    expect($bags['service'])->toBe('CalculatorService');
    expect($bags['method'])->toBe('Add');
    expect($bags['requestType'])->toBe('AddRequest');
    expect($bags['responseType'])->toBe('AddResponse');
    expect($bags['requestFields'])->toHaveCount(2);
    expect($bags['requestFields'][0])->toMatchArray(['name' => 'a', 'type' => 'int32', 'number' => 1]);
    expect($bags['responseFields'][0])->toMatchArray(['name' => 'result', 'type' => 'int32', 'number' => 1]);
});

it('indexes every service method of the described source', function (): void {
    $normalizer = new ProtoSourceNormalizer();
    $index = $normalizer->normalize(protobufSource('calculator', 'https://example.test/calculator.proto'), calcProtoContent());

    expect(array_keys($index))->toBe([
        'CalculatorService/Add',
        '$sourceDescriptions.calculator.CalculatorService/Add',
        'CalculatorService/Divide',
        '$sourceDescriptions.calculator.CalculatorService/Divide',
        'CalculatorService/Ping',
        '$sourceDescriptions.calculator.CalculatorService/Ping',
    ]);
});

it('throws when the raw content is not a proto3 document', function (): void {
    $normalizer = new ProtoSourceNormalizer();

    $normalizer->normalize(protobufSource('broken', 'x.proto'), 'not a proto document');
})->throws(InvalidArgumentException::class);
