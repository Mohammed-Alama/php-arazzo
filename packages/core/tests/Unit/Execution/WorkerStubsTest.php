<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\StepExecutedEvent;

it('has interfaces and events', function (): void {
    expect(interface_exists(ExpressionResolverInterface::class))->toBeTrue()
        ->and(class_exists(StepExecutedEvent::class))->toBeTrue();
});
