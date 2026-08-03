<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\WorkflowAtLeastOneRule;
use Alama\Arazzo\Validation\Rules\WorkflowIdPatternRule;
use Alama\Arazzo\Validation\Rules\WorkflowUniqueIdRule;

function docWithWorkflows(array $workflows): ArazzoDocument
{
    return new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [],
        $workflows,
        new Components([], [], [], []),
        [],
    );
}

function wf(string $id): Workflow
{
    $s = new Step('s', null, 'op', null, null, [], null, [], [], [], []);

    return new Workflow($id, null, null, null, [], [$s], [], [], [], []);
}

it('flags empty workflows list', function (): void {
    $ec = new ErrorCollector();
    $doc = docWithWorkflows([]);
    (new WorkflowAtLeastOneRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags duplicate workflowIds', function (): void {
    $doc = docWithWorkflows([wf('a'), wf('a')]);
    $ec = new ErrorCollector();
    (new WorkflowUniqueIdRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.unique_id');
});

it('flags bad workflowId pattern with spaces', function (): void {
    $doc = docWithWorkflows([wf('bad id!')]);
    $ec = new ErrorCollector();
    (new WorkflowIdPatternRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.id_pattern');
});

it('accepts valid workflowIds without spaces', function (): void {
    $doc = docWithWorkflows([wf('valid_id'), wf('valid-id'), wf('valid.id'), wf('valid:id')]);
    $ec = new ErrorCollector();
    (new WorkflowIdPatternRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
