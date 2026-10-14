<?php

declare(strict_types=1);

arch('cli does not depend on illuminate framework')
    ->expect('Alama\Arazzo\Cli\Console')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Cli\Generator')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Cli\Renderer')
    ->not->toUse('Illuminate');

arch('cli composes only through the runner facade (F2.2)')
    ->expect('Alama\Arazzo\Cli')
    ->not->toUse([
        'Alama\Arazzo\Runner\Sync\StepExecutor',
        'Alama\Arazzo\Runner\Sync\SubWorkflowStepExecutor',
        'Alama\Arazzo\Runner\Async\StepExecutionWorker',
        'Alama\Arazzo\Runner\Async\StepOutcomeHandler',
        'Alama\Arazzo\Runner\Async\CorrelationResumer',
        'Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor',
        'Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor',
    ]);
