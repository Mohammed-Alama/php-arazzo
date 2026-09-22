<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Parser;

use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Parser;

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

    expect($steps[0]->flow->strictValidation)->toBeTrue();
    expect($steps[1]->flow->strictValidation)->toBeFalse();
    expect($steps[2]->flow->strictValidation)->toBeNull();
});

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

    expect($steps[0]->flow->idempotencyKey)->toBeTrue();
    expect($steps[1]->flow->idempotencyKey)->toBeFalse();
    expect($steps[2]->flow->idempotencyKey)->toBeNull();
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

    expect($steps[0]->flow->idempotencyHeader)->toBe('X-Adyen-Idempotency-Key');
    expect($steps[1]->flow->idempotencyHeader)->toBeNull();
});
