<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Loader;

use Alama\Arazzo\Document\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Document\Parser\Loader;

it('throws on missing file', function (): void {
    $l = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
    expect(fn () => $l->load('/nonexistent/path.yaml'))->toThrow(LoaderException::class, 'File not found');
});

it('throws on unreadable file', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'arazzo').'.yaml';
    file_put_contents($tmp, "arazzo: '1.0.0'\ninfo:\n  title: T\n  version: '1'\nworkflows: []\n");
    chmod($tmp, 0);
    try {
        $l = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
        expect(fn () => $l->load($tmp))->toThrow(LoaderException::class, 'File not readable');
    } finally {
        chmod($tmp, 0644);
        @unlink($tmp);
    }
});

it('throws on unsupported extension', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'arazzo').'.txt';
    file_put_contents($tmp, 'foo');
    try {
        $l = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
        expect(fn () => $l->load($tmp))->toThrow(LoaderException::class, 'Unsupported extension');
    } finally {
        @unlink($tmp);
    }
});

it('throws on decode error', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'arazzo').'.json';
    file_put_contents($tmp, '{not json');
    try {
        $l = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
        expect(fn () => $l->load($tmp))->toThrow(LoaderException::class, 'Failed to decode');
    } finally {
        @unlink($tmp);
    }
});

it('throws when root is not an object', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'arazzo').'.json';
    file_put_contents($tmp, '[1,2,3]');
    try {
        $l = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
        expect(fn () => $l->load($tmp))->toThrow(LoaderException::class, 'Root of Arazzo document must be an object');
    } finally {
        @unlink($tmp);
    }
});
