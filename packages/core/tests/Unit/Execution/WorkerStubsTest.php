<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Events\StepExecutedEvent;
use Alama\Arazzo\Interfaces\ExpressionResolverInterface;

it('has interfaces and events', function (): void {
    expect(interface_exists(ExpressionResolverInterface::class))->toBeTrue()
        ->and(class_exists(StepExecutedEvent::class))->toBeTrue();
});
