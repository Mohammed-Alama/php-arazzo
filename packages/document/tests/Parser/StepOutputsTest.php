<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Document\Parser\Parser;

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
        ->and($outputs['plainValue'])->toBeInstanceOf(Expression::class);

    /** @var Selector $extractedId */
    $extractedId = $outputs['extractedId'];
    expect($extractedId->selector)->toBe('$.id')
        ->and($extractedId->type)->toBe(ExpressionType::JsonPath)
        ->and($extractedId->context)->toBe('$response.body')
        ->and($extractedId->version)->toBe('rfc9535');

    /** @var Expression $plainValue */
    $plainValue = $outputs['plainValue'];
    expect($plainValue->raw)->toBe('abc');
});
