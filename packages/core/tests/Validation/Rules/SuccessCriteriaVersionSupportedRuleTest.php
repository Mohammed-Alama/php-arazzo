<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\SuccessCriterion;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\SuccessCriteriaVersionSupportedRule;

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
