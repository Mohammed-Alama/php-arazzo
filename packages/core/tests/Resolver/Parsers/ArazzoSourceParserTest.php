<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution\Parsers;

use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\ArazzoResolvedSource;
use Alama\Arazzo\Resolver\Exceptions\SourceParseException;
use Alama\Arazzo\Resolver\Parsers\ArazzoSourceParser;

it('parses a minimal valid arazzo yaml document', function (): void {
    $parser = new ArazzoSourceParser(new Parser());
    $yaml = <<<'YAML'
        arazzo: "1.0.0"
        info:
          title: "Test Workflow"
          version: "1.0.0"
        sourceDescriptions: []
        workflows:
          - workflowId: "wf1"
            steps: []
        YAML;

    $resolved = $parser->parse($yaml);

    expect($resolved)->toBeInstanceOf(ArazzoResolvedSource::class);
    expect($resolved->extract('/arazzo'))->toBe('1.0.0');
    expect($resolved->extract('/info/title'))->toBe('Test Workflow');
});

it('parses a minimal valid arazzo json document', function (): void {
    $parser = new ArazzoSourceParser(new Parser());
    $json = json_encode([
        'arazzo' => '1.0.0',
        'info' => ['title' => 'JSON Workflow', 'version' => '1.0.0'],
        'sourceDescriptions' => [],
        'workflows' => [
            ['workflowId' => 'wf1', 'steps' => []],
        ],
    ]);

    $resolved = $parser->parse($json);

    expect($resolved)->toBeInstanceOf(ArazzoResolvedSource::class);
    expect($resolved->extract('/info/title'))->toBe('JSON Workflow');
});

it('throws SourceParseException on malformed yaml input', function (): void {
    $parser = new ArazzoSourceParser(new Parser());
    // valid yaml but missing required arazzo fields — will throw ParserException wrapped as SourceParseException
    $parser->parse("invalid: true\nbroken: [unclosed");
})->throws(SourceParseException::class);
