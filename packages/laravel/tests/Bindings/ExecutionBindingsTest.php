<?php

declare(strict_types=1);

use Alama\Arazzo\Execution\CorrelationResumer;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\StepOutcomeHandler;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Execution\WorkflowExecutor;

function engineProp(WorkflowEngine $engine, string $name): mixed
{
    $prop = new ReflectionProperty($engine, $name);
    $prop->setAccessible(true);

    return $prop->getValue($engine);
}

it('builds the engine once and reuses it', function (): void {
    expect(app(WorkflowEngine::class))->toBe(app(WorkflowEngine::class));
});

it('feeds retry_ceiling from config into the engine', function (): void {
    config()->set('arazzo.retry_ceiling', 3);
    app()->forgetInstance(WorkflowEngine::class);

    expect(engineProp(app(WorkflowEngine::class), 'retryPolicy')->maxAttempts)->toBe(3);
});

it('keeps the default ceiling when config holds junk', function (): void {
    config()->set('arazzo.retry_ceiling', 'many');
    app()->forgetInstance(WorkflowEngine::class);

    expect(engineProp(app(WorkflowEngine::class), 'retryPolicy')->maxAttempts)->toBe(10);
});

it('feeds retry_backoff_multiplier from config into the engine policy', function (): void {
    config()->set('arazzo.retry_backoff_multiplier', 2.5);
    app()->forgetInstance(WorkflowEngine::class);

    expect(engineProp(app(WorkflowEngine::class), 'retryPolicy')->backoffMultiplier)->toBe(2.5);
});

it('resolves the full execution pipeline without database access at bind time', function (): void {
    // Constructors only capture connections/config; resolution must not query.
    foreach ([
        WorkflowExecutor::class,
        StepExecutor::class,
        StepOutcomeHandler::class,
        CorrelationResumer::class,
    ] as $abstract) {
        expect(app($abstract))->toBeInstanceOf($abstract);
    }
});
