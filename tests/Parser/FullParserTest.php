<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Dto\Enum\Format;
use Alama\LaravelArazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Exceptions\ParserException;
use Alama\LaravelArazzo\Loader\Loader;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Parser\Parser;

function parseFixture(string $rel): ArazzoDocument
{
    $loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
    $raw = $loader->load(__DIR__ . '/../fixtures/parser/' . $rel);

    return (new Parser())->parse($raw);
}

it('parses a full arazzo document', function (): void {
    $doc = parseFixture('full.yaml');

    expect($doc->arazzo)->toBe('1.0.0')
        ->and($doc->info->title)->toBe('Full')
        ->and($doc->sourceDescriptions[0]->type)->toBe(SourceType::Openapi)
        ->and($doc->workflows)->toHaveCount(1);

    $wf = $doc->workflows[0];
    expect($wf->workflowId)->toBe('main')
        ->and($wf->dependsOn)->toBe(['init'])
        ->and($wf->steps)->toHaveCount(2)
        ->and($wf->parameters[0]->name)->toBe('X-Trace')
        ->and($wf->successActions[0])->toBeInstanceOf(SuccessEndAction::class)
        ->and($wf->failureActions[0])->toBeInstanceOf(Reusable::class)
        ->and($wf->outputs)->toHaveKey('user');

    $s1 = $wf->steps[0];
    expect($s1->operationPath)->toBe('/users/{id}')
        ->and($s1->successCriteria[0]->type)->toBe(CriterionType::Simple)
        ->and($s1->successCriteria[0]->context)->toBe('$response.header.status')
        ->and($s1->onSuccess[0])->toBeInstanceOf(Reusable::class)
        ->and($s1->onFailure[0])->toBeInstanceOf(RetryAction::class);

    expect($doc->components->successActions)->toHaveKey('goEnd')
        ->and($doc->components->failureActions)->toHaveKey('globalFail')
        ->and($doc->specificationExtensions)->toHaveKey('x-vendor-custom');
});

it('parses a minimal arazzo document', function (): void {
    $doc = parseFixture('valid-minimal.yaml');

    expect($doc->arazzo)->toBe('1.0.0')
        ->and($doc->info->title)->toBe('Minimal')
        ->and($doc->workflows)->toBeArray()->toBeEmpty();
});

it('rejects missing workflows', function (): void {
    parseFixture('invalid-missing-workflows.yaml');
})->throws(ParserException::class, 'Missing required field: /workflows');

it('parses AsyncAPI action/channelPath/correlationId fields on a step', function (): void {
    $raw = [
        'arazzo' => '1.0.0',
        'info' => ['title' => 'T', 'version' => '1.0'],
        'sourceDescriptions' => [],
        'workflows' => [
            [
                'workflowId' => 'wf_1',
                'steps' => [
                    [
                        'stepId' => 'wait-for-ride',
                        'action' => 'receive',
                        'channelPath' => 'channels/rides/created',
                        'correlationId' => '{$response.body#/rideId}',
                    ],
                ],
            ],
        ],
    ];

    $document = (new Parser())->parse(new RawDocument($raw, 'memory://test', Format::Json));
    $step = $document->workflows[0]->steps[0];

    expect($step->action)->toBe('receive');
    expect($step->channelPath)->toBe('channels/rides/created');
    expect($step->correlationId->raw)->toBe('{$response.body#/rideId}');
});

it('leaves action/channelPath/correlationId null when absent', function (): void {
    $raw = [
        'arazzo' => '1.0.0',
        'info' => ['title' => 'T', 'version' => '1.0'],
        'sourceDescriptions' => [],
        'workflows' => [
            ['workflowId' => 'wf_1', 'steps' => [['stepId' => 's1', 'operationId' => 'op']]],
        ],
    ];

    $document = (new Parser())->parse(new RawDocument($raw, 'memory://test', Format::Json));
    $step = $document->workflows[0]->steps[0];

    expect($step->action)->toBeNull();
    expect($step->channelPath)->toBeNull();
    expect($step->correlationId)->toBeNull();
});
