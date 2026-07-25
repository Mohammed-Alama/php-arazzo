<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Dto\Selector;
use Alama\LaravelArazzo\Parser\Parser;

it('parses Selectors in parameters and payload replacements', function () {
    $raw = new RawDocument([
        'arazzo' => '1.1.0',
        'info' => ['title' => 't', 'version' => '1'],
        'workflows' => [
            [
                'workflowId' => 'w',
                'parameters' => [
                    [
                        'name' => 'p1',
                        'in' => 'header',
                        'value' => [
                            'type' => 'jsonpath',
                            'selector' => '$.auth',
                        ]
                    ]
                ],
                'steps' => [
                    [
                        'stepId' => 's',
                        'operationId' => 'op',
                        'requestBody' => [
                            'payload' => 'dummy',
                            'replacements' => [
                                [
                                    'target' => '$.user',
                                    'value' => [
                                        'type' => 'xpath',
                                        'selector' => '/user/id',
                                    ]
                                ]
                            ]
                        ]
                    ],
                ],
            ],
        ],
    ], '/tmp/x.yaml', Format::Yaml);

    $doc = (new Parser())->parse($raw);
    
    $param = $doc->workflows[0]->parameters[0];
    expect($param->value)->toBeInstanceOf(Selector::class)
        ->and($param->value->selector)->toBe('$.auth');

    $repl = $doc->workflows[0]->steps[0]->requestBody->replacements[0];
    expect($repl->value)->toBeInstanceOf(Selector::class)
        ->and($repl->value->selector)->toBe('/user/id');
});
