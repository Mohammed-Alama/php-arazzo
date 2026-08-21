<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Exceptions\SourceFetchException;
use Alama\Arazzo\Resolver\Exceptions\SourceParseException;
use Alama\Arazzo\Resolver\SourceFetcher;

it('resolves http url using http fetcher and decodes json', function (): void {
    $httpFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return '{"test": "content"}';
        }
    };
    $resolver = new DefaultSourceResolver(['http' => $httpFetcher]);
    $source = new SourceDescription('test', 'http://example.com/api.json', SourceType::Openapi);
    $result = $resolver->resolve($source, '/base');

    expect($result->name)->toBe('test')
        ->and($result->canonicalUri)->toBe('http://example.com/api.json')
        ->and($result->content)->toBe(['test' => 'content'])
        ->and($httpFetcher->called)->toBeTrue();
});

it('routes url with no scheme to file fetcher', function (): void {
    $fileFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return '{"foo": "bar"}';
        }
    };
    $resolver = new DefaultSourceResolver(['file' => $fileFetcher]);
    $source = new SourceDescription('local', './local/api.json', SourceType::Openapi);
    $result = $resolver->resolve($source, '/base');

    expect($fileFetcher->called)->toBeTrue()
        ->and($result->canonicalUri)->toBe('file:///base/local/api.json');
});

it('throws SourceParseException when content is malformed', function (): void {
    $fetcher = new class() implements SourceFetcher
    {
        public function fetch(string $urlOrPath, string $basePath): string
        {
            return '{invalid json}';
        }
    };
    $resolver = new DefaultSourceResolver(['http' => $fetcher]);
    $source = new SourceDescription('test', 'http://example.com/api.json', SourceType::Openapi);

    expect(fn () => $resolver->resolve($source, '/base'))
        ->toThrow(SourceParseException::class);
});

it('throws SourceFetchException for unknown scheme', function (): void {
    $fetcher = new class() implements SourceFetcher
    {
        public function fetch(string $urlOrPath, string $basePath): string
        {
            return '{}';
        }
    };
    $resolver = new DefaultSourceResolver(['http' => $fetcher]);
    $source = new SourceDescription('s3', 's3://my-bucket/api.json', SourceType::Openapi);

    expect(fn () => $resolver->resolve($source, '/base'))
        ->toThrow(SourceFetchException::class, 's3');
});

it('resolves relative URLs with parent directories to canonical URIs', function (): void {
    $fileFetcher = new class() implements SourceFetcher
    {
        public function fetch(string $urlOrPath, string $basePath): string
        {
            return '{"foo": "bar"}';
        }
    };
    $resolver = new DefaultSourceResolver(['file' => $fileFetcher]);
    $source = new SourceDescription('local', '../sibling/api.json', SourceType::Openapi);
    $result = $resolver->resolve($source, '/path/to/base');

    expect($result->canonicalUri)->toBe('file:///path/to/sibling/api.json');
});
