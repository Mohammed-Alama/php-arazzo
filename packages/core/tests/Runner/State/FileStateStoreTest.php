<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\State\FileStateStore;
use Alama\Arazzo\Runner\State\InMemoryStateStore;

if (!function_exists('tempStateDir')) {
    function tempStateDir(): string
    {
        $dir = sys_get_temp_dir() . '/arazzo-state-' . bin2hex(random_bytes(4));

        return $dir;
    }
}

it('persists, loads, and deletes execution state as JSON files', function (): void {
    $store = new FileStateStore(tempStateDir());

    $store->save('exec/1:a', ['steps' => ['s1' => ['statusCode' => 200]], 'falsy' => false, 'zero' => 0]);

    $loaded = $store->load('exec/1:a');
    expect($loaded)->not->toBeNull()
        ->and($loaded['steps']['s1']['statusCode'])->toBe(200)
        ->and($loaded['falsy'])->toBeFalse()
        ->and($loaded['zero'])->toBe(0);

    $store->delete('exec/1:a');
    expect($store->load('exec/1:a'))->toBeNull();
});

it('returns null for unknown executions and ignores TTL by design', function (): void {
    $store = new FileStateStore(tempStateDir());

    expect($store->load('missing'))->toBeNull();

    $store->save('e1', ['ok' => true], ttlSeconds: 1); // CLI resume must outlive any TTL
    expect($store->load('e1')['ok'])->toBeTrue();
});

it('sanitizes hostile execution ids into safe filenames', function (): void {
    $dir = tempStateDir();
    $store = new FileStateStore($dir);

    $store->save('../../etc/passwd', ['x' => 1]);

    $path = $store->path('../../etc/passwd');
    expect(file_exists(dirname($dir, 2) . '/passwd.json'))->toBeFalse()
        ->and(dirname($path))->toBe($dir)
        ->and(str_contains($path, '/../'))->toBeFalse()
        ->and($store->load('../../etc/passwd'))->not->toBeNull();
});

it('keeps in-memory state process-local and simple', function (): void {
    $store = new InMemoryStateStore();

    $store->save('exec_1', ['n' => 5]);
    expect($store->load('exec_1')['n'])->toBe(5);

    $store->delete('exec_1');
    expect($store->load('exec_1'))->toBeNull();
});
