<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Parser\Parser;

it('parses x-strict-validation boolean from a step', function (): void {
    $yaml = <<<'YAML'
    arazzo: 1.0.0
    info: { title: "Test", version: "1.0.0" }
    sourceDescriptions: []
    workflows:
      - workflowId: test
        steps:
          - stepId: step1
            operationId: op1
            x-strict-validation: true
          - stepId: step2
            operationId: op2
            x-strict-validation: false
          - stepId: step3
            operationId: op3
    YAML;

    $decoder = new SymfonyYamlDecoder();
    $raw = new RawDocument($decoder->decode($yaml), 'memory://test', Format::Yaml);
    $document = (new Parser())->parse($raw);
    $steps = $document->workflows[0]->steps;

    expect($steps[0]->strictValidation)->toBeTrue();
    expect($steps[1]->strictValidation)->toBeFalse();
    expect($steps[2]->strictValidation)->toBeNull();
});

use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Dto\Enum\Format;

it('parses x-idempotency-key boolean from a step', function (): void {
    $yaml = <<<'YAML'
    arazzo: 1.0.0
    info: { title: "Test", version: "1.0.0" }
    sourceDescriptions: []
    workflows:
      - workflowId: test
        steps:
          - stepId: step1
            operationId: op1
            x-idempotency-key: true
          - stepId: step2
            operationId: op2
            x-idempotency-key: false
          - stepId: step3
            operationId: op3
    YAML;

    $decoder = new SymfonyYamlDecoder();
    $raw = new RawDocument($decoder->decode($yaml), 'memory://test', Format::Yaml);
    $document = (new Parser())->parse($raw);
    $steps = $document->workflows[0]->steps;

    expect($steps[0]->idempotencyKey)->toBeTrue();
    expect($steps[1]->idempotencyKey)->toBeFalse();
    expect($steps[2]->idempotencyKey)->toBeNull();
});

it('parses x-idempotency-header string from a step', function (): void {
    $yaml = <<<'YAML'
    arazzo: 1.0.0
    info: { title: "Test", version: "1.0.0" }
    sourceDescriptions: []
    workflows:
      - workflowId: test
        steps:
          - stepId: step1
            operationId: op1
            x-idempotency-header: X-Adyen-Idempotency-Key
          - stepId: step2
            operationId: op2
    YAML;

    $decoder = new SymfonyYamlDecoder();
    $raw = new RawDocument($decoder->decode($yaml), 'memory://test', Format::Yaml);
    $document = (new Parser())->parse($raw);
    $steps = $document->workflows[0]->steps;

    expect($steps[0]->idempotencyHeader)->toBe('X-Adyen-Idempotency-Key');
    expect($steps[1]->idempotencyHeader)->toBeNull();
});
