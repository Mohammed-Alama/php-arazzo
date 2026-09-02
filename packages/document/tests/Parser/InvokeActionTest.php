<?php

declare(strict_types=1);

use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\RawDocument;

it('parses invoke action in onSuccess and onFailure', function () {
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
                        'onSuccess' => [
                            [
                                'name' => 'sub1',
                                'type' => 'invoke',
                                'workflowId' => 'child-workflow',
                                'parameters' => [
                                    'p1' => 'abc',
                                ],
                            ],
                        ],
                        'onFailure' => [
                            [
                                'name' => 'sub2',
                                'type' => 'invoke',
                                'workflowId' => 'child-2',
                                'version' => '2.0',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ], '/tmp/x.yaml', Format::Yaml);

    $doc = (new Parser())->parse($raw);

    $success = $doc->workflows[0]->steps[0]->onSuccess[0];
    expect($success)->toBeInstanceOf(SubWorkflowSuccessAction::class)
        ->and($success->workflowId)->toBe('child-workflow')
        ->and($success->parameters['p1']->raw)->toBe('abc'); // parsed via parseValueOrSelector string expression logic

    $fail = $doc->workflows[0]->steps[0]->onFailure[0];
    expect($fail)->toBeInstanceOf(SubWorkflowFailureAction::class)
        ->and($fail->workflowId)->toBe('child-2')
        ->and($fail->version)->toBe('2.0')
        ->and($fail->parameters)->toBe([]);
});
