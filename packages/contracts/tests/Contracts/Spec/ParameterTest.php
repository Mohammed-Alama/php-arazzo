<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\ValueMode;
use Alama\Arazzo\Contracts\Spec\Parameter;

it('supports metadata and variable locations plus an explicit value mode', function (): void {
    $parameter = new Parameter('operation', ParameterIn::Metadata, 'GetToken', ValueMode::Literal);

    expect($parameter->name)->toBe('operation')
        ->and($parameter->in)->toBe(ParameterIn::Metadata)
        ->and($parameter->value)->toBe('GetToken')
        ->and($parameter->valueMode)->toBe(ValueMode::Literal);
});

it('defaults valueMode to null', function (): void {
    $parameter = new Parameter('op', ParameterIn::Query, 1);

    expect($parameter->valueMode)->toBeNull();
});
