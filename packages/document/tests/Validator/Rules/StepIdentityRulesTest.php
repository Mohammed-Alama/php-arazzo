<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepAtLeastOneRule;
use Alama\Arazzo\Document\Validator\Rules\StepIdPatternRule;
use Alama\Arazzo\Document\Validator\Rules\StepNestedWorkflowExistsRule;
use Alama\Arazzo\Document\Validator\Rules\StepOperationIdSourceScopedRule;
use Alama\Arazzo\Document\Validator\Rules\StepOperationPathSyntaxRule;
use Alama\Arazzo\Document\Validator\Rules\StepOperationTargetPresentRule;
use Alama\Arazzo\Document\Validator\Rules\StepUniqueIdRule;
use Alama\Arazzo\Expression\SymbolTable;

function stepIdMk(string $id, ?string $opId = 'op', ?string $opPath = null, ?string $wfId = null): Step
{
    return new Step($id, null, $opId, $opPath, $wfId, [], null, [], [], [], []);
}
function stepIdWf(string $id, array $steps, array $dep = []): Workflow
{
    return new Workflow($id, null, null, null, $dep, $steps, [], [], [], []);
}
function stepIdDoc(array $wfs, array $sources = []): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), $sources, $wfs, new Components([], [], [], []), []);
}

it('flags empty step list', function (): void {
    $doc = stepIdDoc([stepIdWf('a', [])]);
    $ec = new ErrorCollector();
    (new StepAtLeastOneRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags duplicate stepId', function (): void {
    $doc = stepIdDoc([stepIdWf('a', [stepIdMk('x'), stepIdMk('x')])]);
    $ec = new ErrorCollector();
    (new StepUniqueIdRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags bad stepId pattern', function (): void {
    $doc = stepIdDoc([stepIdWf('a', [stepIdMk('bad!')])]);
    $ec = new ErrorCollector();
    (new StepIdPatternRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('requires exactly one operation target', function (): void {
    $doc = stepIdDoc([stepIdWf('a', [stepIdMk('x', null, null, null)])]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);

    $doc2 = stepIdDoc([stepIdWf('a', [stepIdMk('x', 'op', 'src#/paths/x/get')])]);
    $ec2 = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($doc2, SymbolTable::build($doc2), $ec2);
    expect($ec2->errors())->toHaveCount(1);
});

it('requires single openapi source for unqualified operationId', function (): void {
    $doc = stepIdDoc([stepIdWf('a', [stepIdMk('x', 'op')])], []);
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);

    $doc2 = stepIdDoc(
        [stepIdWf('a', [stepIdMk('x', 'src1#op')])],
        [new SourceDescription('src1', '/a', SourceType::Openapi)],
    );
    $ec2 = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc2, SymbolTable::build($doc2), $ec2);
    expect($ec2->errors())->toBe([]);
});

it('validates operationPath syntax', function (): void {
    $doc = stepIdDoc([stepIdWf('a', [stepIdMk('x', null, 'nosource-no-hash')])]);
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags unresolved nested workflow', function (): void {
    $doc = stepIdDoc([stepIdWf('a', [stepIdMk('x', null, null, 'ghost')])]);
    $ec = new ErrorCollector();
    (new StepNestedWorkflowExistsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
