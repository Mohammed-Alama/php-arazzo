<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\SubWorkflowInvokeTargetResolvesRule;

function docWithInvoke(string $targetId, array $workflowIds = ['w', 'ride-reconcile']): ArazzoDocument
{
    $workflows = array_map(fn ($id) => new Workflow($id, null, null, null, [], [
        StepFactory::http('s', null, new StepFlow(), new StepIo(), operationId: 'op'),
    ], [], [], [], []), $workflowIds);

    // Attach invoke onSuccess to the first workflow's first step
    $step = StepFactory::http('s', null, new StepFlow(onSuccess: [
        new SubWorkflowSuccessAction('call', $targetId, [], []),
    ]), new StepIo(), operationId: 'op');
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
