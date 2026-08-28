<?php

declare(strict_types=1);

namespace Tests\Validator;

use Alama\Arazzo\Expression\Enum\SourceType;
use Alama\Arazzo\Expression\SourceDescription;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\RuleSet;
use Alama\Arazzo\Validator\Validator;

function timeoutDoc(string $arazzoVersion): ArazzoDocument
{
    $step = new Step(
        stepId: 'timed',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        dependsOn: [],
        action: null,
        channelPath: null,
        correlationId: null,
        strictValidation: null,
        idempotencyKey: null,
        idempotencyHeader: null,
        timeout: 4500,
    );

    return new ArazzoDocument(
        arazzo: $arazzoVersion,
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', 'https://api.test/openapi.json', SourceType::Openapi)],
        workflows: [Fx::wf('main', [$step])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        rawRoot: null,
        specVersion: str_starts_with($arazzoVersion, '1.1') ? SpecVersion::V1_1 : SpecVersion::V1_0,
    );
}

it('parses timeout in milliseconds on steps', function (): void {
    expect(timeoutDoc('1.1.0')->workflows[0]->steps[0]->timeout)->toBe(4500);
});

it('flags timeout on 1.0 documents and non-positive values', function (): void {
    $validator = new Validator(RuleSet::default());

    $on10 = $validator->validate(timeoutDoc('1.0.0'));
    $codes = array_map(fn ($e) => $e->code, $on10->errors);
    expect($on10->isValid())->toBeFalse()
        ->and($codes)->toContain('step.timeout_requires_11');

    // 1.1 with a positive value is clean
    $on11 = $validator->validate(timeoutDoc('1.1.0'));
    expect($on11->isValid())->toBeTrue();
});
