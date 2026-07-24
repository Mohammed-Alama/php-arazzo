<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\IdempotencyKeyInjector;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;

function idempotencyStep(?bool $idempotencyKey = null, ?string $idempotencyHeader = null): Step
{
    return new Step(
        stepId: 'step-a',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        idempotencyKey: $idempotencyKey,
        idempotencyHeader: $idempotencyHeader,
    );
}

function idempotencyContext(): WorkflowContext
{
    return new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
}

it('returns the request unchanged when the feature is disabled globally with no step override', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: false, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');

    $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect($result->request)->toBe($request);
    expect($result->key)->toBeNull();
    expect($result->header)->toBeNull();
});

it('returns the request unchanged when the step opts out even if the global default is on', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');

    $result = $injector->inject($request, idempotencyStep(idempotencyKey: false), idempotencyContext());

    expect($result->request)->toBe($request);
    expect($result->key)->toBeNull();
    expect($result->header)->toBeNull();
});

it('returns the request unchanged for non-mutating methods even when enabled', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    foreach (['GET', 'HEAD', 'OPTIONS', 'PUT', 'TRACE'] as $method) {
        $request = new Request($method, 'https://api.example.com/charges');
        $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

        expect($result->request)->toBe($request);
        expect($result->key)->toBeNull();
        expect($result->header)->toBeNull();
    }
});
