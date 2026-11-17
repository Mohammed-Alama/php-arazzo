<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\Http\Registrar\ProtocolHttpRegistrar;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\Sync\StepExecutor;
use ReflectionProperty;

/**
 * ADR-001: strict response validation is the platform default. The config file
 * and both explicit consumers must agree, or the same step validates
 * differently depending on which adapter executes it.
 */
it('defaults strict schema validation on in the published config', function (): void {
    expect(config('arazzo.strict_schema_validation'))->toBeTrue();
});

it('passes the strict default to the async graph seams', function (): void {
    $executor = app(AsyncExecutionGraph::class)->stepExecutor();

    expect((new ReflectionProperty(StepExecutor::class, 'strictValidationDefault'))->getValue($executor))->toBeTrue();
});

it('passes the strict default to the protocol http executor', function (): void {
    $http = app(ProtocolHttpRegistrar::class)->executors()[0];

    expect($http)->toBeInstanceOf(HttpStepExecutor::class)
        ->and((new ReflectionProperty(HttpStepExecutor::class, 'strictValidationDefault'))->getValue($http))->toBeTrue();
});
