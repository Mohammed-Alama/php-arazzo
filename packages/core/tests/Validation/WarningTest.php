<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation;

use Alama\Arazzo\Validation\Warning;

it('Warning toArray returns full shape', function (): void {
    $w = new Warning('code.w', 'wmsg', '/x', 5);
    expect($w->toArray())->toBe(['code' => 'code.w', 'message' => 'wmsg', 'path' => '/x', 'line' => 5]);
});

it('Warning defaults line to null', function (): void {
    $w = new Warning('c', 'm', '/p');
    expect($w->line)->toBeNull();
});
