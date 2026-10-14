<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Rpc\Normalizer;

use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoField;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoMethod;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoParser;
use Alama\Arazzo\Protocol\Rpc\Normalizer\ProtoService;
use InvalidArgumentException;

function calcProtoContent(): string
{
    return file_get_contents(__DIR__.'/../Fixtures/calculator.proto') ?: '';
}

it('parses the package, services, methods, and messages of a .proto document', function (): void {
    $doc = (new ProtoParser())->parse(calcProtoContent());

    expect($doc->package)->toBe('demo.calculator');
    expect(array_map(static fn (ProtoService $s): string => $s->name, $doc->services))->toBe(['CalculatorService']);

    $service = $doc->services[0];
    expect(array_map(static fn (ProtoMethod $m): string => $m->name, $service->methods))->toBe(['Add', 'Divide', 'Ping']);

    $add = $service->methods[0];
    expect($add)->toBeInstanceOf(ProtoMethod::class);
    expect($add->requestType)->toBe('AddRequest');
    expect($add->responseType)->toBe('AddResponse');
    expect($add->requestStreaming)->toBeFalse();
    expect($add->responseStreaming)->toBeFalse();

    expect(array_keys($doc->messages))->toBe(['AddRequest', 'AddResponse', 'DivideRequest', 'DivideResponse']);

    $addRequest = $doc->messages['AddRequest'];
    expect(array_map(static fn (ProtoField $f): string => $f->name, $addRequest))->toBe(['a', 'b']);
    expect($addRequest[0]->type)->toBe('int32');
    expect($addRequest[0]->number)->toBe(1);
    expect($addRequest[1]->repeated)->toBeFalse();
});

it('tolerates comments, imports, options, and enums around the declarations', function (): void {
    $doc = (new ProtoParser())->parse(calcProtoContent());

    expect($doc->package)->toBe('demo.calculator');
    expect(count($doc->services))->toBe(1);
    expect(count($doc->messages))->toBe(4);
});

it('records streaming on rpc methods', function (): void {
    $proto = <<<'PROTO'
    syntax = "proto3";

    service Chat {
      rpc Talk (stream Request) returns (stream Response);
    }

    message Request { string text = 1; }
    message Response { string text = 1; }
    PROTO;

    $doc = (new ProtoParser())->parse($proto);

    expect($doc->services[0]->methods[0]->requestStreaming)->toBeTrue();
    expect($doc->services[0]->methods[0]->responseStreaming)->toBeTrue();
});

it('throws when the content is not a proto3 document', function (): void {
    (new ProtoParser())->parse('this is not a proto file at all');
})->throws(InvalidArgumentException::class, 'proto3');

it('throws for a proto2 syntax header', function (): void {
    (new ProtoParser())->parse("syntax = \"proto2\";\npackage stale;\nservice Old { rpc Run (In) returns (Out); }\nmessage In { string x = 1; }\nmessage Out { string y = 1; }");
})->throws(InvalidArgumentException::class, 'proto3');
