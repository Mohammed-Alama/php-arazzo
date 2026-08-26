<?php

declare(strict_types=1);

use Alama\Arazzo\Console\DocumentLoader;
use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ArazzoExpressionResolver;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Events\RunStarted;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\ArazzoSchemaValidator;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use Alama\Arazzo\Validator\PreflightFailureException;
use Alama\Arazzo\Validator\PreflightValidator;
use GuzzleHttp\Psr7\HttpFactory;

const INPUTS_SCHEMA_DOC = __DIR__.'/../fixtures/inputs-schema/workflow.arazzo.yaml';

it('accepts inputs matching the declared schema', function (): void {
    $document = DocumentLoader::load(INPUTS_SCHEMA_DOC);
    $validator = preflightForInputsDoc();

    $result = $validator->validateInputs($document, 'bookRide', ['rideId' => 42, 'rider' => 'sam']);

    expect($result->isValid())->toBeTrue(json_encode($result->errors));
});

it('rejects missing required and wrong-typed inputs before execution', function (): void {
    $document = DocumentLoader::load(INPUTS_SCHEMA_DOC);
    $validator = preflightForInputsDoc();

    // rideId as string violates the integer type; rider is not required.
    $result = $validator->validateInputs($document, 'bookRide', ['rideId' => 'not-an-int']);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors[0]->code)->toBe('preflight.inputs_schema')
        ->and($result->errors[0]->path)->toStartWith('/workflows/bookRide/inputs');
});

it('treats documents without an inputs schema as unconstrained', function (): void {
    $document = DocumentLoader::load(__DIR__.'/../fixtures/loader/minimal.yaml');
    $validator = preflightForInputsDoc();

    $result = $validator->validateInputs($document, 'wf', ['anything' => ['goes' => true]]);

    expect($result->isValid())->toBeTrue();
});

it('blocks executor runs on invalid inputs before any event fires', function (): void {
    $events = new SimpleEventDispatcher();
    $fired = [];
    $events->subscribe(RunStarted::class, function (object $e) use (&$fired): void {
        $fired[] = $e::class;
    });

    $document = DocumentLoader::load(INPUTS_SCHEMA_DOC);

    $evaluator = new ExpressionEvaluator();
    $registry = new SourceRegistry(new DefaultSourceResolver([]));
    $operationResolver = new OpenApiOperationResolver(
        new OpenApiDocumentLoader($registry),
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );
    $resolver = new ArazzoExpressionResolver(
        $evaluator,
        new ArazzoOutputExtractor($operationResolver, $evaluator),
        new ArazzoCriteriaEvaluator($evaluator),
        new ArazzoSchemaValidator($operationResolver),
    );

    $executor = new WorkflowExecutor(
        new StepExecutor(
            new DefaultOpenApiExecutor(new FakePsr18Client(), new HttpFactory()),
            $resolver,
            $operationResolver,
        ),
        workflowEngine: new WorkflowEngine($resolver),
        events: $events,
        preflight: preflightForInputsDoc(),
    );

    try {
        $executor->execute(
            $document->workflows[0],
            $document,
            ['rideId' => 'wrong-type'],
        );

        $this->fail('expected PreflightFailureException');
    } catch (PreflightFailureException $e) {
        expect($e->result->errors[0]->code)->toBe('preflight.inputs_schema')
            ->and($fired)->toBe([]); // nothing executed, not even RunStarted
    }
});

function preflightForInputsDoc(): PreflightValidator
{
    $registry = new SourceRegistry(new DefaultSourceResolver([]));

    return new PreflightValidator(
        $registry,
        new OpenApiOperationResolver(
            new OpenApiDocumentLoader($registry),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        ),
        new DomXpathEvaluator(),
    );
}
