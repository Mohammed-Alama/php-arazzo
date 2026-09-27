<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\RequestPipeline\Data\ExecutionEvaluationInput;

function evaluationInputDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('exposes the workflow context it was built with', function (): void {
    $context = new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
    $input = new ExecutionEvaluationInput($context, 'step-a');

    expect($input->getWorkflowContext())->toBe($context)
        ->and($input->workflowContext)->toBe($context);
});

it('defaults the current step id and document to null', function (): void {
    $input = new ExecutionEvaluationInput(new WorkflowContext('def-1'));

    expect($input->getCurrentStepId())->toBeNull()
        ->and($input->getDocument())->toBeNull();
});

it('carries the current step id and document when supplied', function (): void {
    $document = evaluationInputDocument();
    $input = new ExecutionEvaluationInput(new WorkflowContext('def-1'), 'step-a', $document);

    expect($input->getCurrentStepId())->toBe('step-a')
        ->and($input->getDocument())->toBe($document)
        ->and($input->currentStepId)->toBe('step-a')
        ->and($input->document)->toBe($document);
});

it('satisfies the evaluation input contract', function (): void {
    expect(new ExecutionEvaluationInput(new WorkflowContext('def-1')))
        ->toBeInstanceOf(EvaluationInputInterface::class);
});
