<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Parser\Parser;

it('parses root $self URI', function () {
    $raw = new RawDocument([
        'arazzo' => '1.1.0',
        '$self' => 'https://example.com/spec.arazzo.yaml',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [['workflowId' => 'w', 'steps' => [['stepId' => 's', 'operationId' => 'op']]]],
    ], '/tmp/x.yaml', Format::Yaml);

    $doc = (new Parser())->parse($raw);
    expect($doc->self)->toBe('https://example.com/spec.arazzo.yaml');
});

it('leaves $self null when absent', function () {
    $raw = new RawDocument([
        'arazzo' => '1.1.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [['workflowId' => 'w', 'steps' => [['stepId' => 's', 'operationId' => 'op']]]],
    ], '/tmp/x.yaml', Format::Yaml);

    expect((new Parser())->parse($raw)->self)->toBeNull();
});
