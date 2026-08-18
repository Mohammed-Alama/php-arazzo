<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation;

use Alama\Arazzo\Validator\Error;

it('Error toArray returns full shape', function (): void {
    $e = new Error('code.a', 'msg', '/p', 12);
    expect($e->toArray())->toBe(['code' => 'code.a', 'message' => 'msg', 'path' => '/p', 'line' => 12]);
});

it('Error defaults line to null', function (): void {
    $e = new Error('c', 'm', '/p');
    expect($e->line)->toBeNull()->and($e->toArray()['line'])->toBeNull();
});
