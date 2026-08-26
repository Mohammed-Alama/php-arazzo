<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Events\StepExecuted;

it('has interfaces and events', function (): void {
    expect(interface_exists(ExpressionResolverInterface::class))->toBeTrue()
        ->and(class_exists(StepExecuted::class))->toBeTrue();
});
