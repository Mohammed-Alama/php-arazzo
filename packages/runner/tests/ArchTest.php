<?php

declare(strict_types=1);
use Alama\Arazzo\Runner\Execution\Data\InjectionResult;
use Alama\Arazzo\Runner\Execution\ExpressionValueResolver;
use Alama\Arazzo\Runner\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Runner\Execution\ParameterSerializer;
use Alama\Arazzo\Runner\Execution\RequestCompiler;
use Alama\Arazzo\Runner\Execution\ReusableParameterResolver;
use Alama\Arazzo\Runner\Execution\StepParameterMerger;
use Alama\Arazzo\Runner\Execution\TypeCaster;

arch('runner does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\Jobs')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Runner\Protocol')
    ->not->toUse('Illuminate');

arch('runner does not leak expression internals')
    ->expect('Alama\Arazzo\Expression')
    ->not->toUse('Alama\Arazzo\Expression\EvaluationEngine')
    ->expect('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->not->toUse('Alama\Arazzo\Cli\Generator')
    ->not->toUse('Alama\Arazzo\Cli\Renderer')
    ->expect('Alama\Arazzo\Runner\Jobs')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->expect('Alama\Arazzo\Runner\Protocol')
    ->not->toUse('Alama\Arazzo\Cli\Console');

arch('runner does not depend on cebe directly')
    ->expect('Alama\Arazzo\Runner')
    ->not->toUse('cebe');

arch('folded request pipeline stays transport-agnostic')
    ->expect([
        RequestCompiler::class,
        ExpressionValueResolver::class,
        IdempotencyKeyInjector::class,
        ReusableParameterResolver::class,
        StepParameterMerger::class,
        ParameterSerializer::class,
        TypeCaster::class,
        InjectionResult::class,
    ])
    ->not->toUse('Alama\Arazzo\Engine')
    ->not->toUse('Illuminate')
    ->not->toUse('cebe\openapi')
    ->not->toUse('GuzzleHttp');
