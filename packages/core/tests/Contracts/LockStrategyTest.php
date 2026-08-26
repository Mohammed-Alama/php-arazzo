<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\FileLockStrategy;
use Alama\Arazzo\Contracts\NullLockStrategy;

it('runs the callback under a file lock and releases afterwards', function (): void {
    $lock = new FileLockStrategy(tempStateDir());
    $calls = 0;

    $result = $lock->acquire('exec_1', 30, function () use (&$calls): string {
        $calls++;

        return 'inside';
    });

    expect($result)->toBe('inside')
        ->and($calls)->toBe(1)
        ->and($lock->tryAcquire('other', 30))->toBeTrue();
});

it('propagates exceptions thrown inside the locked callback and still releases', function (): void {
    $lock = new FileLockStrategy(tempStateDir());

    $lock->acquire('exec_x', 30, function (): void {
        throw new RuntimeException('boom');
    });
})->throws(RuntimeException::class, 'boom');

it('null lock is a pass-through for tests and single-process runs', function (): void {
    $lock = new NullLockStrategy();
    $ran = false;

    expect($lock->acquire('k', 10, function () use (&$ran): bool {
        $ran = true;

        return true;
    }))->toBeTrue()
        ->and($ran)->toBeTrue()
        ->and($lock->tryAcquire('k', 10))->toBeTrue();
});
