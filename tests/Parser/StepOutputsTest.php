<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\ExpressionType;
use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Dto\Selector;
use Alama\LaravelArazzo\Parser\Parser;

it('parses 1.1.0 Selector objects in step outputs', function () {
    $raw = new RawDocument([
        'arazzo' => '1.1.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [
            [
                'workflowId' => 'w',
                'steps' => [
                    [
                        'stepId' => 's',
                        'operationId' => 'op',
                        'outputs' => [
                            'extractedId' => [
                                'context' => '$response.body',
                                'type' => 'jsonpath',
                                'selector' => '$.id',
                                'version' => 'rfc9535',
                            ],
                            'plainValue' => 'abc',
                        ],
                    ],
                ],
            ],
        ],
    ], '/tmp/x.yaml', Format::Yaml);

    $doc = (new Parser())->parse($raw);
    $outputs = $doc->workflows[0]->steps[0]->outputs;

    expect($outputs)->toHaveKey('extractedId')
        ->and($outputs['extractedId'])->toBeInstanceOf(Selector::class)
        ->and($outputs['extractedId']->selector)->toBe('$.id')
        ->and($outputs['extractedId']->type)->toBe(ExpressionType::JsonPath)
        ->and($outputs['extractedId']->context)->toBe('$response.body')
        ->and($outputs['extractedId']->version)->toBe('rfc9535')
        ->and($outputs['plainValue'])->toBeInstanceOf(\Alama\LaravelArazzo\Dto\Expression::class)
        ->and($outputs['plainValue']->raw)->toBe('abc');
});
