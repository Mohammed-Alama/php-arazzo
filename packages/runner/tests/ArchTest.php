<?php

declare(strict_types=1);
use Alama\Arazzo\Engine\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Engine\Execution\ParameterSerializer;
use Alama\Arazzo\Engine\Execution\RequestCompiler;
use Alama\Arazzo\Engine\Execution\ReusableParameterResolver;
use Alama\Arazzo\Engine\Execution\StepParameterMerger;
use Alama\Arazzo\Engine\Execution\TypeCaster;
use Alama\Arazzo\Runner\Execution\Data\InjectionResult;

arch('runner does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\Sync')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\Async')
    ->not->toUse('Illuminate');

arch('runner does not leak expression internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Expression\EvaluationEngine')
    ->expect('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Cli\Generator')
    ->not->toUse('Alama\Arazzo\Cli\Renderer')
    ->expect('Alama\Arazzo\Runner\Sync')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Cli\Generator')
    ->not->toUse('Alama\Arazzo\Cli\Renderer')
    ->expect('Alama\Arazzo\Runner\Async')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Cli\Generator')
    ->not->toUse('Alama\Arazzo\Cli\Renderer');

arch('runner does not depend on cebe directly')
    ->expect('Alama\Arazzo\Runner')
    ->not->toUse('cebe\openapi');

// Pest arch evaluates `not->toUse` per expectation target: a multi-target
// class list only fails when EVERY listed class violates (one clean class
// swallows the check), so each folded class gets its own single-target
// segment. The Alama\Arazzo\Engine leg from the original guard is gone:
// these classes now live inside that namespace, so same-namespace
// references (e.g. RequestCompiler -> ReusableParameterResolver) resolve
// to Alama\Arazzo\Engine\* FQCNs and would flag the guard at baseline.
arch('folded request pipeline stays transport-agnostic')
    ->expect(RequestCompiler::class)
    ->not->toUse(['Illuminate', 'cebe\openapi', 'GuzzleHttp', 'GuzzleHttp\Psr7'])
    ->expect(IdempotencyKeyInjector::class)
    ->not->toUse(['Illuminate', 'cebe\openapi', 'GuzzleHttp', 'GuzzleHttp\Psr7'])
    ->expect(ReusableParameterResolver::class)
    ->not->toUse(['Illuminate', 'cebe\openapi', 'GuzzleHttp', 'GuzzleHttp\Psr7'])
    ->expect(StepParameterMerger::class)
    ->not->toUse(['Illuminate', 'cebe\openapi', 'GuzzleHttp', 'GuzzleHttp\Psr7'])
    ->expect(ParameterSerializer::class)
    ->not->toUse(['Illuminate', 'cebe\openapi', 'GuzzleHttp', 'GuzzleHttp\Psr7'])
    ->expect(TypeCaster::class)
    ->not->toUse(['Illuminate', 'cebe\openapi', 'GuzzleHttp', 'GuzzleHttp\Psr7'])
    ->expect(InjectionResult::class)
    ->not->toUse(['Illuminate', 'cebe\openapi', 'GuzzleHttp', 'GuzzleHttp\Psr7']);

arch('sync path is queue-free')
    ->expect('Alama\Arazzo\Runner\Sync')
    ->not->toUse(['Alama\Arazzo\Runner\Async', 'Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface']);

arch('async path may compose the sync path')
    ->expect('Alama\Arazzo\Runner\Async')
    ->toUse('Alama\Arazzo\Runner\Sync');

arch('nothing below the facade depends on the facade')
    ->expect('Alama\Arazzo\Runner\Sync')
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade')
    ->expect('Alama\Arazzo\Runner\Async')
    ->not->toUse('Alama\Arazzo\Runner\RunnerFacade');
