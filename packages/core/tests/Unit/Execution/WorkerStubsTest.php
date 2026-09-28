<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Events\StepExecutedEvent;

it('has interfaces and events', function (): void {
    expect(interface_exists(EvaluationEngineInterface::class))->toBeTrue()
        ->and(class_exists(StepExecutedEvent::class))->toBeTrue();
});
