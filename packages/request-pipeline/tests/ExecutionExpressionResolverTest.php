<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\RequestPipeline\Data\ExecutionEvaluationInput;
use Alama\Arazzo\RequestPipeline\ExecutionExpressionResolver;

function resolverFor(
    EvaluationEngineInterface $engine,
    ?OutputExtractorInterface $extractor = null,
    ?ResponseValidatorInterface $validator = null,
): ExecutionExpressionResolver {
    return new ExecutionExpressionResolver(
        $engine,
        $extractor ?? \Mockery::mock(OutputExtractorInterface::class),
        $validator ?? \Mockery::mock(ResponseValidatorInterface::class),
    );
}

function resolverStep(): Step
{
    return StepFactory::http('step-a', null, new StepFlow(), new StepIo(), 'op');
}

function executionResolverDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [new Workflow('wf-1', null, null, null, [], [], [], [], [], [])],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('delegates evaluate to the engine, carrying the step id in the evaluation input', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $expression = new Expression('$inputs.x');
    $context = new WorkflowContext('def-1');
    $seen = null;

    $engine->shouldReceive('evaluate')
        ->once()
        ->with($expression, \Mockery::type(EvaluationInputInterface::class))
        ->andReturnUsing(function (Expression $expression, EvaluationInputInterface $input) use (&$seen): string {
            $seen = $input;

            return 'result';
        });

    $value = resolverFor($engine)->evaluate($expression, $context, 'step-a');

    expect($value)->toBe('result')
        ->and($seen)->toBeInstanceOf(ExecutionEvaluationInput::class)
        ->and($seen->getCurrentStepId())->toBe('step-a')
        ->and($seen->getWorkflowContext())->toBe($context);
});

it('delegates extractOutputs to the output extractor', function (): void {
    $extractor = \Mockery::mock(OutputExtractorInterface::class);
    $extractor->shouldReceive('extractOutputs')
        ->once()
        ->with(\Mockery::type(Step::class), \Mockery::type(WorkflowContext::class), \Mockery::type(ArazzoDocument::class))
        ->andReturn(['id' => 'ch_1']);

    $outputs = resolverFor(\Mockery::mock(EvaluationEngineInterface::class), $extractor)
        ->extractOutputs(resolverStep(), new WorkflowContext('def-1'), executionResolverDocument());

    expect($outputs)->toBe(['id' => 'ch_1']);
});

it('delegates evaluateSuccessCriteria to the engine', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluateSuccessCriteria')
        ->once()
        ->with(\Mockery::type(Step::class), \Mockery::type(WorkflowContext::class), null)
        ->andReturnTrue();

    expect(resolverFor($engine)->evaluateSuccessCriteria(resolverStep(), new WorkflowContext('def-1')))->toBeTrue();
});

it('delegates evaluateCriteria to the engine', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('evaluateCriteria')
        ->once()
        ->with(['condition' => '{$inputs.ok}'], \Mockery::type(Step::class), \Mockery::type(WorkflowContext::class), null)
        ->andReturnFalse();

    $result = resolverFor($engine)->evaluateCriteria(
        ['condition' => '{$inputs.ok}'],
        resolverStep(),
        new WorkflowContext('def-1'),
    );

    expect($result)->toBeFalse();
});

it('delegates validateResponseSchema to the response validator', function (): void {
    $validator = \Mockery::mock(ResponseValidatorInterface::class);
    $validator->shouldReceive('validateResponseSchema')
        ->once()
        ->with(
            \Mockery::type(Step::class),
            201,
            'application/json',
            ['id' => 'ch_1'],
            \Mockery::type(ArazzoDocument::class),
        );

    resolverFor(\Mockery::mock(EvaluationEngineInterface::class), null, $validator)
        ->validateResponseSchema(resolverStep(), 201, 'application/json', ['id' => 'ch_1'], executionResolverDocument());
});
