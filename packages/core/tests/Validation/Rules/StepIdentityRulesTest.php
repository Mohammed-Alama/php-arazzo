<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\StepAtLeastOneRule;
use Alama\Arazzo\Validation\Rules\StepIdPatternRule;
use Alama\Arazzo\Validation\Rules\StepNestedWorkflowExistsRule;
use Alama\Arazzo\Validation\Rules\StepOperationIdSourceScopedRule;
use Alama\Arazzo\Validation\Rules\StepOperationPathSyntaxRule;
use Alama\Arazzo\Validation\Rules\StepOperationTargetPresentRule;
use Alama\Arazzo\Validation\Rules\StepUniqueIdRule;

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
