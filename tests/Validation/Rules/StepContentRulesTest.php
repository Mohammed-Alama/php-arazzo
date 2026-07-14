<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\StepCriteriaTypeContextRule;
use Alama\LaravelArazzo\Validation\Rules\StepParametersHaveNameRule;
use Alama\LaravelArazzo\Validation\Rules\StepRequestBodyReplacementsTargetRule;
use Alama\LaravelArazzo\Validation\Rules\StepSuccessCriteriaConditionRule;

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
