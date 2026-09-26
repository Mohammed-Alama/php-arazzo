<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec\Enum;

use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;

it('exposes the three interaction modes', function (): void {
    expect(InteractionMode::Form->value)->toBe('form');
    expect(InteractionMode::Redirect->value)->toBe('redirect');
    expect(InteractionMode::Acknowledge->value)->toBe('acknowledge');
});
