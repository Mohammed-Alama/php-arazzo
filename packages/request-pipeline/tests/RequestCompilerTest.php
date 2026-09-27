<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\RequestPipeline\ExpressionValueResolver;
use Alama\Arazzo\RequestPipeline\RequestCompiler;
use Closure;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function requestCompilerDocument(): ArazzoDocument
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

function requestCompilerFor(EvaluationEngineInterface $engine): RequestCompiler
{
    return new RequestCompiler(new ExpressionValueResolver($engine), $engine);
}

function requestCompilerStep(array $parameters, ?RequestBody $requestBody = null): Step
{
    return StepFactory::http(
        stepId: 'step-a',
        description: null,
        flow: new StepFlow(),
        io: new StepIo(parameters: $parameters, requestBody: $requestBody),
        operationId: 'op',
    );
}

it('routes each parameter into the payload bucket its location names', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')->andReturnUsing(
        static fn (string $value): string => match ($value) {
            '{$inputs.p}' => 'path-value',
            '{$inputs.q}' => 'query-value',
            '{$inputs.h}' => 'header-value',
            '{$inputs.a}' => 'auto-value',
            default => $value,
        },
    );

    $step = requestCompilerStep([
        new Parameter('p', ParameterIn::Path, '{$inputs.p}'),
        new Parameter('q', ParameterIn::Query, '{$inputs.q}'),
        new Parameter('h', ParameterIn::Header, '{$inputs.h}'),
        new Parameter('a', ParameterIn::Body, '{$inputs.a}'),
    ]);

    $result = requestCompilerFor($engine)->compile($step, requestCompilerDocument(), new WorkflowContext('def-1'));

    expect($result['payload']->path)->toBe(['p' => 'path-value'])
        ->and($result['payload']->query)->toBe(['q' => 'query-value'])
        ->and($result['payload']->header)->toBe(['h' => 'header-value'])
        ->and($result['payload']->auto)->toBe(['a' => 'auto-value'])
        ->and($result['payload']->body)->toBeNull();
});

it('records every resolved value under its parameter name', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')->andReturnUsing(
        static fn (string $value): string => str_replace(['{$inputs.', '}'], '', $value),
    );

    $step = requestCompilerStep([
        new Parameter('first', ParameterIn::Query, '{$inputs.first}'),
        new Parameter('second', ParameterIn::Query, '{$inputs.second}'),
    ]);

    $result = requestCompilerFor($engine)->compile($step, requestCompilerDocument(), new WorkflowContext('def-1'));

    expect($result['resolvedInputs'])->toBe(['first' => 'first', 'second' => 'second']);
});

it('resolves payload replacements into the body through the callback it hands the engine', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.amount}', \Mockery::type(WorkflowContext::class), 'step-a')
        ->andReturn('100');

    // Stand in for the engine: drive the replacement the way it would, so this
    // asserts the compiler wired its own resolver into the callback position.
    $engine->shouldReceive('replacePayload')
        ->once()
        ->with(\Mockery::type(Step::class), ['amount' => 0], \Mockery::type(Closure::class), \Mockery::type(WorkflowContext::class))
        ->andReturnUsing(static function (Step $step, array $body, Closure $resolve, WorkflowContext $context): array {
            $replacement = $step->io->requestBody->replacements[0];

            return [$replacement->target => $resolve($replacement)];
        });

    $step = requestCompilerStep([], new RequestBody(
        contentType: 'application/json',
        payload: ['amount' => 0],
        replacements: [new PayloadReplacement('/amount', '{$inputs.amount}')],
    ));

    $result = requestCompilerFor($engine)->compile($step, requestCompilerDocument(), new WorkflowContext('def-1'));

    expect($result['payload']->body)->toBe(['/amount' => '100']);
});

it('keeps the body null when the engine replaces nothing', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('replacePayload')->once()->andReturn([]);

    $step = requestCompilerStep([], new RequestBody(
        contentType: 'application/json',
        payload: ['amount' => 0],
        replacements: [],
    ));

    $result = requestCompilerFor($engine)->compile($step, requestCompilerDocument(), new WorkflowContext('def-1'));

    expect($result['payload']->body)->toBeNull();
});

it('builds the canonical request record from the request and the payload', function (): void {
    $request = new Request('POST', 'https://api.example.com/charges?expand=true', ['X-Trace' => 'abc'], '{"amount":100}');

    $record = RequestCompiler::requestRecord($request, new OpenApiPayload(
        path: ['id' => 'ch_1'],
        body: ['amount' => 100],
    ));

    expect($record['method'])->toBe('POST')
        ->and($record['url'])->toBe('https://api.example.com/charges?expand=true')
        ->and($record['query'])->toBe(['expand' => 'true'])
        ->and($record['path'])->toBe(['id' => 'ch_1'])
        // Guzzle derives Host from the URI and stores it first.
        ->and($record['headers'])->toBe(['Host' => 'api.example.com', 'X-Trace' => 'abc'])
        ->and($record['body'])->toBe(['amount' => 100]);
});

it('tolerates a null captured request in the canonical record', function (): void {
    $record = RequestCompiler::requestRecord(null, new OpenApiPayload());

    expect($record['method'])->toBeNull()
        ->and($record['url'])->toBe('')
        ->and($record['query'])->toBe([])
        ->and($record['path'])->toBe([])
        ->and($record['headers'])->toBe([])
        ->and($record['body'])->toBe([]);
});

it('falls back to an empty body when the payload is not an array', function (): void {
    $record = RequestCompiler::requestRecord(null, new OpenApiPayload(body: 'raw text'));

    expect($record['body'])->toBe([]);
});

it('decodes a JSON response and reports its canonical fields', function (): void {
    $response = new Response(201, ['Content-Type' => 'application/json'], '{"id":"ch_1"}');

    $decoded = RequestCompiler::decodeResponse($response);

    expect($decoded['statusCode'])->toBe(201)
        ->and($decoded['contentType'])->toBe('application/json')
        ->and($decoded['body'])->toBe(['id' => 'ch_1'])
        ->and($decoded['rawBody'])->toBe('{"id":"ch_1"}')
        ->and($decoded['headers'])->toHaveKey('Content-Type');
});

it('falls back to an empty body when the response is not JSON', function (): void {
    $decoded = RequestCompiler::decodeResponse(new Response(500, [], 'upstream exploded'));

    expect($decoded['statusCode'])->toBe(500)
        ->and($decoded['body'])->toBe([])
        ->and($decoded['rawBody'])->toBe('upstream exploded')
        ->and($decoded['contentType'])->toBe('');
});

it('flattens multi-value headers and skips non-string keys', function (): void {
    $flat = RequestCompiler::flattenHeaders([
        'X-Multi' => ['a', 'b'],
        'X-Single' => ['only'],
        'X-Skipped' => 'not-an-array',
    ]);

    expect($flat)->toBe(['X-Multi' => 'a, b', 'X-Single' => 'only']);
});
