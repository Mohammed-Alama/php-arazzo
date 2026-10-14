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
use Alama\Arazzo\Document\Validator\Rules\SuccessCriteriaVersionSupportedRule;

function versionRuleDoc(SuccessCriterion $c): ArazzoDocument
{
    $step = StepFactory::http('x', null, new StepFlow(), new StepIo(successCriteria: [$c]), operationId: 'op');
    $w = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), []);
}

it('accepts recognised xpath versions', function (string $version): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, $version);
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
})->with(['xpath-10', 'xpath-20', 'xpath-30', 'xpath-31']);

it('rejects an unrecognised xpath version', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, 'xpath-99');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->message)->toContain('xpath-99')
        ->and($ec->errors()[0]->message)->toContain('xpath-31');
});

it('accepts xpath with no version pinned', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, null);
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});

it('ignores version on a non-xpath criterion type', function (): void {
    $c = new SuccessCriterion(null, '$.id', CriterionType::JsonPath, 'xpath-99');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});
