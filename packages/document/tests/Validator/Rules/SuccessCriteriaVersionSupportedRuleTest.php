<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\SuccessCriteriaVersionSupportedRule;
use Alama\Arazzo\Expression\SymbolTable;

function versionRuleDoc(SuccessCriterion $c): ArazzoDocument
{
    $step = new Step('x', null, 'op', null, null, [], null, [$c], [], [], []);
    $w = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$w], new Components([], [], [], []), []);
}

it('rejects xpath-30', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, 'xpath-30');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->message)->toContain('xpath-30');
});

it('rejects xpath-31', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, 'xpath-31');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('accepts xpath-10', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, 'xpath-10');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});

it('accepts xpath with no version pinned', function (): void {
    $c = new SuccessCriterion(null, '/users/id', CriterionType::XPath, null);
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});

it('ignores version on a non-xpath criterion type', function (): void {
    $c = new SuccessCriterion(null, '$.id', CriterionType::JsonPath, 'xpath-30');
    $doc = versionRuleDoc($c);
    $ec = new ErrorCollector();

    (new SuccessCriteriaVersionSupportedRule())->check($doc, SymbolTable::build($doc), $ec);

    expect($ec->errors())->toHaveCount(0);
});
