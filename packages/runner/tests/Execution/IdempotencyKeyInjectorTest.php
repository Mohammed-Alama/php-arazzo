<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Spec\Step;
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

it('injects a deterministic key and the default header on POST/PATCH/DELETE when enabled', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    foreach (['POST', 'PATCH', 'DELETE'] as $method) {
        $request = new Request($method, 'https://api.example.com/charges', [], '{"amount":100}');

        $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

        expect($result->key)->not->toBeNull();
        expect($result->key)->toMatch('/^[0-9a-f]{64}$/');
        expect($result->header)->toBe('Idempotency-Key');
        expect($result->request->getHeaderLine('Idempotency-Key'))->toBe($result->key);
    }
});

it('produces the same key across two calls with the same request identity and payload', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');

    $a = $injector->inject($request, idempotencyStep(), idempotencyContext());
    $b = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
});

it('produces different keys when the body changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":1}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":2}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->not->toBe($b->key);
});

it('produces different keys when the URI changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":1}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/y', [], '{"a":1}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->not->toBe($b->key);
});

it('produces different keys when the stepId changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $ctx = idempotencyContext();
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $a = $injector->inject($request, idempotencyStep(), $ctx);
    $stepB = new Step(
        stepId: 'step-b',
        description: null, operationId: 'op', operationPath: null, workflowId: null,
        parameters: [], requestBody: null, successCriteria: [], onSuccess: [], onFailure: [], outputs: [],
    );
    $b = $injector->inject($request, $stepB, $ctx);

    expect($a->key)->not->toBe($b->key);
});

it('produces different keys when the definitionId or workflowId changes', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $a = $injector->inject($request, idempotencyStep(), new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'));
    $b = $injector->inject($request, idempotencyStep(), new WorkflowContext('def-2', [], [], [], 'wf-1', 'exec-1'));
    $c = $injector->inject($request, idempotencyStep(), new WorkflowContext('def-1', [], [], [], 'wf-2', 'exec-1'));

    expect($a->key)->not->toBe($b->key);
    expect($a->key)->not->toBe($c->key);
});

it('produces the same key for two JSON bodies that differ only in top-level key order', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"a":1,"b":2}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"b":2,"a":1}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
});

it('produces the same key for JSON bodies with nested-object key reordering', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"outer":{"a":1,"b":2}}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"outer":{"b":2,"a":1}}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
});

it('does NOT reorder list arrays (positional semantics preserved)', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"items":[1,2,3]}'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], '{"items":[3,2,1]}'), idempotencyStep(), idempotencyContext());

    expect($a->key)->not->toBe($b->key);
});

it('falls back to raw bytes for a non-JSON body and still produces a deterministic key', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');

    $a = $injector->inject(new Request('POST', 'https://api.example.com/x', [], 'raw-non-json-body'), idempotencyStep(), idempotencyContext());
    $b = $injector->inject(new Request('POST', 'https://api.example.com/x', [], 'raw-non-json-body'), idempotencyStep(), idempotencyContext());
    $c = $injector->inject(new Request('POST', 'https://api.example.com/x', [], 'different-raw-body'), idempotencyStep(), idempotencyContext());

    expect($a->key)->toBe($b->key);
    expect($a->key)->not->toBe($c->key);
});

it('uses the step-level x-idempotency-header override when set', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $result = $injector->inject($request, idempotencyStep(idempotencyHeader: 'X-Adyen-Idempotency-Key'), idempotencyContext());

    expect($result->header)->toBe('X-Adyen-Idempotency-Key');
    expect($result->request->getHeaderLine('X-Adyen-Idempotency-Key'))->toBe($result->key);
    expect($result->request->getHeaderLine('Idempotency-Key'))->toBe('');
});

it('overwrites the configured header if the request already carries it', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', ['Idempotency-Key' => 'manual-value'], '{"a":1}');

    $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect($result->key)->not->toBe('manual-value');
    expect($result->request->getHeaderLine('Idempotency-Key'))->toBe($result->key);
});

it('rewinds the request body stream after reading so downstream sending sees an unread body', function (): void {
    $injector = new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key');
    $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');

    $result = $injector->inject($request, idempotencyStep(), idempotencyContext());

    expect((string) $result->request->getBody())->toBe('{"a":1}');
});
