<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepSuccessCriteriaConditionRule;

function critDoc(SuccessCriterion $crit): ArazzoDocument
{
    $step = StepFactory::http(
        'x', null,
        new StepFlow(),
        new StepIo(successCriteria: [$crit]),
        operationId: 'op',
    );
    $w = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), []);
}

it('flags empty condition', function (): void {
    $doc = critDoc(new SuccessCriterion(null, '   ', null));
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags simple condition missing a valid operator', function (): void {
    $doc = critDoc(new SuccessCriterion(null, 'status', CriterionType::Simple));
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.success_criteria_condition')
        ->and($ec->errors()[0]->message)->toContain('missing a valid operator');
});

it('flags simple condition using invalid assignment operator', function (): void {
    $doc = critDoc(new SuccessCriterion(null, 'status = 200', CriterionType::Simple));
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.success_criteria_condition')
        ->and($ec->errors()[0]->message)->toContain('invalid assignment operator');
});

it('accepts simple condition with a valid operator', function (): void {
    $doc = critDoc(new SuccessCriterion(null, 'status == 200', CriterionType::Simple));
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('skips operator check for non-simple criterion type', function (): void {
    $doc = critDoc(new SuccessCriterion(null, 'status', CriterionType::JsonPath));
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
