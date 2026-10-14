<?php

declare(strict_types=1);

arch('laravel composes only through the runner facade (F2.2)')
    ->expect('Alama\Arazzo\Laravel')
    ->not->toUse([
        'Alama\Arazzo\Runner\Sync\StepExecutor',
        'Alama\Arazzo\Runner\Sync\SubWorkflowStepExecutor',
        'Alama\Arazzo\Runner\Async\StepExecutionWorker',
        'Alama\Arazzo\Runner\Async\StepOutcomeHandler',
        'Alama\Arazzo\Runner\Async\CorrelationResumer',
        'Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor',
        'Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor',
    ]);
