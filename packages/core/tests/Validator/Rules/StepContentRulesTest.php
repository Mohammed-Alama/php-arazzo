<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\PayloadReplacement;
use Alama\Arazzo\Dto\RequestBody;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\SuccessCriterion;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepCriteriaTypeContextRule;
use Alama\Arazzo\Validator\Rules\StepParametersHaveNameRule;
use Alama\Arazzo\Validator\Rules\StepRequestBodyReplacementsTargetRule;
use Alama\Arazzo\Validator\Rules\StepSuccessCriteriaConditionRule;

function stepContentDoc(Step $s): ArazzoDocument
{
    $w = new Workflow('w', null, null, null, [], [$s], [], [], [], []);

    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), []);
}

it('flags empty parameter name', function (): void {
    $step = new Step('x', null, 'op', null, null, [new Parameter('', ParameterIn::Query, 'v')], null, [], [], [], []);
    $doc = stepContentDoc($step);
    $ec = new ErrorCollector();
    (new StepParametersHaveNameRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad replacement target', function (): void {
    $body = new RequestBody(null, [], [new PayloadReplacement('no-slash', 'v')]);
    $step = new Step('x', null, 'op', null, null, [], $body, [], [], [], []);
    $doc = stepContentDoc($step);
    $ec = new ErrorCollector();
    (new StepRequestBodyReplacementsTargetRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags whitespace-only condition', function (): void {
    $crit = new SuccessCriterion(null, '   ', null);
    $step = new Step('x', null, 'op', null, null, [], null, [$crit], [], [], []);
    $doc = stepContentDoc($step);
    $ec = new ErrorCollector();
    (new StepSuccessCriteriaConditionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags jsonpath criterion missing context', function (): void {
    $crit = new SuccessCriterion(null, '$.id != null', CriterionType::JsonPath);
    $step = new Step('x', null, 'op', null, null, [], null, [$crit], [], [], []);
    $doc = stepContentDoc($step);
    $ec = new ErrorCollector();
    (new StepCriteriaTypeContextRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
