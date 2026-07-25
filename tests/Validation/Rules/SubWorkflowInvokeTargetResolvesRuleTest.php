<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\SubWorkflowFailureAction;
use Alama\LaravelArazzo\Dto\Action\SubWorkflowSuccessAction;
use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\SubWorkflowInvokeTargetResolvesRule;

function docWithInvoke(string $targetId, array $workflowIds = ['w', 'ride-reconcile']): ArazzoDocument
{
    $workflows = array_map(fn ($id) => new Workflow($id, null, null, null, [], [
        new Step('s', null, 'op', null, null, [], null, [], [], [], []),
    ], [], [], [], []), $workflowIds);

    // Attach invoke onSuccess to the first workflow's first step
    $step = new Step('s', null, 'op', null, null, [], null, [], [
        new SubWorkflowSuccessAction('call', $targetId, [], []),
    ], [], []);
    $workflows[0] = new Workflow($workflowIds[0], null, null, null, [], [$step], [], [], [], []);

    return new ArazzoDocument(
        arazzo: '1.1.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: $workflows,
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );
}

it('accepts invoke targeting an existing workflow', function () {
    $errors = new ErrorCollector();
    $doc = docWithInvoke('ride-reconcile');
    (new SubWorkflowInvokeTargetResolvesRule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->errors())->toBe([]);
});

it('errors when invoke target does not resolve', function () {
    $errors = new ErrorCollector();
    $doc = docWithInvoke('ghost-workflow');
    (new SubWorkflowInvokeTargetResolvesRule())->check(
        $doc,
        SymbolTable::build($doc), $errors,
    );
    expect($errors->errors())->not->toBe([]);
});

it('errors when component invoke target does not resolve', function () {
    $doc = new ArazzoDocument(
        arazzo: '1.1.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [],
        components: new Components([], [], [
            'bad' => new SubWorkflowSuccessAction('call', 'ghost-workflow', [], []),
        ], [
            'bad_fail' => new SubWorkflowFailureAction('call', 'ghost-workflow', [], []),
        ]),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );
    $errors = new ErrorCollector();
    (new SubWorkflowInvokeTargetResolvesRule())->check($doc, SymbolTable::build($doc), $errors);
    expect(count($errors->errors()))->toBe(2);
});
