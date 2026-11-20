<?php

declare(strict_types=1);

// The event DTOs live flat in the Alama\Arazzo\Events namespace, alongside the
// Listener/ and Interfaces/ sub-namespaces (which are plumbing, not DTOs), so
// the DTOs are listed explicitly rather than asserting over the whole namespace.
arch('event DTOs are strictly typed and readonly')
    ->expect([
        'Alama\Arazzo\Events\CorrelationPendingEvent',
        'Alama\Arazzo\Events\CorrelationResumedEvent',
        'Alama\Arazzo\Events\RunCompletedEvent',
        'Alama\Arazzo\Events\RunFailedEvent',
        'Alama\Arazzo\Events\RunStartedEvent',
        'Alama\Arazzo\Events\StepExecutedEvent',
        'Alama\Arazzo\Events\StepFailedEvent',
        'Alama\Arazzo\Events\StepRetriedEvent',
        'Alama\Arazzo\Events\StepStartedEvent',
    ])
    ->toBeReadonly()
    ->toUseStrictTypes();

arch('events package stays framework agnostic')
    ->expect('Alama\Arazzo\Events')
    ->not->toUse('Illuminate')
    ->expect('Alama\Arazzo\Events\Listener')
    ->not->toUse('Illuminate')
    ->not->toUse('Alama\Arazzo\Runner');
