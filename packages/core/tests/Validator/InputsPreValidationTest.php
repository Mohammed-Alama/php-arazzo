<?php

declare(strict_types=1);

use Alama\Arazzo\Cli\Console\DocumentLoader;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Document\Document;
use Alama\Arazzo\Document\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Document\Validator\Exceptions\PreflightFailureException;
use Alama\Arazzo\Expression\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Expression\Evaluation\ExpressionResolver;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Runner\Events\RunStartedEvent;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\ResponseSchemaValidator;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutputExtractor;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use GuzzleHttp\Psr7\HttpFactory;

const INPUTS_SCHEMA_DOC = __DIR__.'/../fixtures/inputs-schema/workflow.arazzo.yaml';

it('accepts inputs matching the declared schema', function (): void {
    $document = DocumentLoader::load(INPUTS_SCHEMA_DOC);
    $validator = preflightForInputsDoc();

    $result = $validator->preflightInputs($document, 'bookRide', ['rideId' => 42, 'rider' => 'sam']);

    expect($result->isValid())->toBeTrue(json_encode($result->errors));
});

it('rejects missing required and wrong-typed inputs before execution', function (): void {
    $document = DocumentLoader::load(INPUTS_SCHEMA_DOC);
    $validator = preflightForInputsDoc();

    // rideId as string violates the integer type; rider is not required.
    $result = $validator->preflightInputs($document, 'bookRide', ['rideId' => 'not-an-int']);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors[0]->code)->toBe('preflight.inputs_schema')
        ->and($result->errors[0]->path)->toStartWith('/workflows/bookRide/inputs');
});

it('treats documents without an inputs schema as unconstrained', function (): void {
    $document = DocumentLoader::load(__DIR__.'/../fixtures/loader/minimal.yaml');
    $validator = preflightForInputsDoc();

    $result = $validator->preflightInputs($document, 'wf', ['anything' => ['goes' => true]]);

    expect($result->isValid())->toBeTrue();
});

it('blocks executor runs on invalid inputs before any event fires', function (): void {
    $events = new SimpleEventDispatcher();
    $fired = [];
    $events->subscribe(RunStartedEvent::class, function (object $e) use (&$fired): void {
        $fired[] = $e::class;
    });

    $document = DocumentLoader::load(INPUTS_SCHEMA_DOC);

    $evaluator = new ExpressionEvaluator();
    $engine = new ExpressionEngine();
    $documents = new Document(null, null, new SourceRegistry(new DefaultSourceResolver([])));
    $resolver = new ExpressionResolver(
        $evaluator,
        new StepOutputExtractor($documents, $engine),
        new CriteriaEvaluator($evaluator),
        new ResponseSchemaValidator($documents),
    );

    $executor = new WorkflowExecutor(
        new StepExecutor(
            new DefaultOpenApiExecutor(new FakePsr18Client(), new HttpFactory()),
            $resolver,
            $documents,
            engine: $engine,
        ),
        workflowEngine: new WorkflowEngine($resolver),
        events: $events,
        preflight: $documents,
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
            ->and($fired)->toBe([]); // nothing executed, not even RunStartedEvent
    }
});

function preflightForInputsDoc(): Document
{
    return new Document(null, null, new SourceRegistry(new DefaultSourceResolver([])));
}
