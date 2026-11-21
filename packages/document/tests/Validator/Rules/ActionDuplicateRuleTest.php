<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureEndAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\ActionDuplicateRule;

function duplicateStep(string $stepId, array $onSuccess = [], array $onFailure = []): Step
{
    return new Step(
        stepId: $stepId,
        description: null,
        target: new StepTarget(),
        flow: new StepFlow(onSuccess: $onSuccess, onFailure: $onFailure),
        io: new StepIo(),
    );
}

function duplicateWorkflow(array $steps = [], array $successActions = [], array $failureActions = []): Workflow
{
    return new Workflow(
        workflowId: 'wf',
        summary: null,
        description: null,
        inputs: null,
        dependsOn: [],
        steps: $steps,
        successActions: $successActions,
        failureActions: $failureActions,
        outputs: [],
        parameters: [],
    );
}

function duplicateDoc(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.1.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [$workflow],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );
}

it('keeps the reserved rule code', function (): void {
    $r = new ActionDuplicateRule();
    expect($r->code())->toBe('step.actions_duplicate');
});

it('rejects duplicate step onSuccess action names', function (): void {
    $errors = new ErrorCollector();
    $doc = duplicateDoc(duplicateWorkflow(
        steps: [duplicateStep('one', onSuccess: [
            new SuccessEndAction('go', []),
            new SuccessEndAction('go', []),
        ])],
    ));
    (new ActionDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('step.actions_duplicate')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/onSuccess')
        ->and($errors->errors()[0]->message)->toContain('indices 0 and 1');
});

it('rejects duplicate step onFailure action names', function (): void {
    $errors = new ErrorCollector();
    $doc = duplicateDoc(duplicateWorkflow(
        steps: [duplicateStep('one', onFailure: [
            new FailureEndAction('go', []),
            new FailureEndAction('go', []),
        ])],
    ));
    (new ActionDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('step.actions_duplicate')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/onFailure');
});

it('rejects duplicate workflow successActions', function (): void {
    $errors = new ErrorCollector();
    $doc = duplicateDoc(duplicateWorkflow(
        successActions: [
            new SuccessEndAction('go', []),
            new SuccessEndAction('go', []),
        ],
    ));
    (new ActionDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('workflow.actions_duplicate')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/successActions');
});

it('rejects duplicate workflow failureActions', function (): void {
    $errors = new ErrorCollector();
    $doc = duplicateDoc(duplicateWorkflow(
        failureActions: [
            new FailureEndAction('go', []),
            new FailureEndAction('go', []),
        ],
    ));
    (new ActionDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('workflow.actions_duplicate')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/failureActions');
});

it('accepts distinct action names in step onSuccess', function (): void {
    $errors = new ErrorCollector();
    $doc = duplicateDoc(duplicateWorkflow(
        steps: [duplicateStep('one', onSuccess: [
            new SuccessEndAction('go', []),
            new SuccessEndAction('stop', []),
        ])],
    ));
    (new ActionDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
});

it('accepts distinct action names in workflow successActions', function (): void {
    $errors = new ErrorCollector();
    $doc = duplicateDoc(duplicateWorkflow(
        successActions: [
            new SuccessEndAction('go', []),
            new SuccessEndAction('stop', []),
        ],
    ));
    (new ActionDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
});

it('skips reusable step actions', function (): void {
    $errors = new ErrorCollector();
    $doc = duplicateDoc(duplicateWorkflow(
        steps: [duplicateStep('one', onSuccess: [
            new Reusable('$components.actions.x'),
            new Reusable('$components.actions.x'),
        ])],
    ));
    (new ActionDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toBe([]);
});
