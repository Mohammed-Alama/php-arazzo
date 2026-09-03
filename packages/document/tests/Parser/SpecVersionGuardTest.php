<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Exceptions\ParserException;
use Alama\Arazzo\Document\Parser\Parser;

function minimalDoc(string $version): RawDocument
{
    return new RawDocument([
        'arazzo' => $version,
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [
            ['workflowId' => 'w', 'steps' => [['stepId' => 's', 'operationId' => 'op']]],
        ],
    ], '/tmp/x.yaml', Format::Yaml);
}

it('accepts 1.0.x', function (string $v) {
    $doc = (new Parser())->parse(minimalDoc($v));
    expect($doc->specVersion)->toBe(SpecVersion::V1_0)
        ->and($doc->arazzo)->toBe($v);
})->with(['1.0.0', '1.0.7']);

it('accepts 1.1.x', function (string $v) {
    $doc = (new Parser())->parse(minimalDoc($v));
    expect($doc->specVersion)->toBe(SpecVersion::V1_1);
})->with(['1.1.0', '1.1.3']);

it('rejects unsupported versions', function (string $v) {
    (new Parser())->parse(minimalDoc($v));
})->throws(ParserException::class)->with(['0.9.0', '1.2.0', '2.0.0', 'abc']);
