<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ValueMode;

it('lists the parameter value modes')
    ->expect(ValueMode::cases())
    ->toBe([ValueMode::Literal, ValueMode::Selector]);

it('uses spec-exact string values')
    ->expect(ValueMode::Literal->value)->toBe('literal')
    ->and(ValueMode::Selector->value)->toBe('selector');
