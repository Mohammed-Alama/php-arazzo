<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution\Fetchers;

use Alama\Arazzo\Resolver\Exceptions\SourceFetchException;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;

$tempDir = null;

beforeEach(function () use (&$tempDir): void {
    $tempDir = sys_get_temp_dir() . '/arazzo-local-fetcher-test-' . uniqid('', true);
    mkdir($tempDir);
    $this->tempDir = $tempDir;
});

afterEach(function (): void {
    foreach (glob($this->tempDir . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->tempDir);
});

it('fetches a local file by relative path', function (): void {
    file_put_contents($this->tempDir . '/test.json', '{"test": true}');

    $fetcher = new LocalFetcher();
    $content = $fetcher->fetch('test.json', $this->tempDir);

    expect($content)->toBe('{"test": true}');
});

it('fetches a local file by absolute path', function (): void {
    $absPath = $this->tempDir . '/abs.json';
    file_put_contents($absPath, '{"abs": 1}');

    $fetcher = new LocalFetcher();
    $content = $fetcher->fetch($absPath, '');

    expect($content)->toBe('{"abs": 1}');
});

it('throws SourceFetchException on missing local file', function (): void {
    $fetcher = new LocalFetcher();
    $fetcher->fetch('missing.json', $this->tempDir);
})->throws(SourceFetchException::class);
