<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ReceiveStepSuccessCriteriaRule;

/**
 * @param  list<SuccessCriterion>  $criteria
 */
function receiveRuleDocument(
    SpecVersion $sv,
    ?string $action,
    array $criteria = [],
): ArazzoDocument {
    $step = new Step(
        'wait',
        null,
        new StepTarget(action: $action, channelPath: 'channels/x'),
        new StepFlow(),
        new StepIo(successCriteria: $criteria),
    );

    return new ArazzoDocument(
        arazzo: $sv->value,
        info: new Info('t', null, null, '1'),
        sourceDescriptions: [],
        workflows: [new Workflow('w', null, null, null, [], [$step], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: $sv,
    );
}

it('errors on an empty-criteria receive step in strict mode', function (): void {
    $errors = new ErrorCollector();
    $doc = receiveRuleDocument(SpecVersion::V1_1, 'receive');

    (new ReceiveStepSuccessCriteriaRule(strict: true))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('step.receive_missing_success_criteria')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/successCriteria');
});

it('warns on an empty-criteria receive step in non-strict mode', function (): void {
    $errors = new ErrorCollector();
    $doc = receiveRuleDocument(SpecVersion::V1_1, 'receive');

    (new ReceiveStepSuccessCriteriaRule(strict: false))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->warnings())->toHaveCount(1)
        ->and($errors->errors())->toBe([])
        ->and($errors->warnings()[0]->code)->toBe('step.receive_missing_success_criteria');
});

it('accepts a receive step that declares criteria', function (): void {
    $errors = new ErrorCollector();
    $criterion = new SuccessCriterion(null, '$response.body.ok == true', CriterionType::Simple);
    $doc = receiveRuleDocument(SpecVersion::V1_1, 'receive', [$criterion]);

    (new ReceiveStepSuccessCriteriaRule(strict: true))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toBe([])
        ->and($errors->warnings())->toBe([]);
});

it('ignores send steps', function (): void {
    $errors = new ErrorCollector();
    $doc = receiveRuleDocument(SpecVersion::V1_1, 'send');

    (new ReceiveStepSuccessCriteriaRule(strict: true))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toBe([])
        ->and($errors->warnings())->toBe([]);
});

it('ignores 1.0 documents so the version rule owns the error', function (): void {
    $errors = new ErrorCollector();
    $doc = receiveRuleDocument(SpecVersion::V1_0, 'receive');

    (new ReceiveStepSuccessCriteriaRule(strict: true))->check(
        $doc,
        SymbolTable::build($doc),
        $errors,
    );

    expect($errors->errors())->toBe([])
        ->and($errors->warnings())->toBe([]);
});
