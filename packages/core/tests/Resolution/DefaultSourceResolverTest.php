<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Resolution\DefaultSourceResolver;
use Alama\Arazzo\Resolution\Exceptions\SourceFetchException;
use Alama\Arazzo\Resolution\Exceptions\SourceParseException;
use Alama\Arazzo\Resolution\ResolvedSource;
use Alama\Arazzo\Resolution\SourceFetcher;
use Alama\Arazzo\Resolution\SourceParser;

it('resolves http url using http fetcher and the matching parser', function (): void {
    $resolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return 'extracted';
        }
    };

    $httpFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'remote content';
        }
    };

    $fileFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'local content';
        }
    };

    $parser = new class($resolved) implements SourceParser
    {
        public function __construct(private readonly ResolvedSource $resolved)
        {
        }

        public function parse(string $content): ResolvedSource
        {
            return $this->resolved;
        }
    };

    $resolver = new DefaultSourceResolver(
        fetchers: [
            'http' => $httpFetcher,
            'https' => $httpFetcher,
            'file' => $fileFetcher,
        ],
        parsers: [SourceType::Openapi->value => $parser],
    );

    $source = new SourceDescription('test', 'http://example.com/api.json', SourceType::Openapi);
    $result = $resolver->resolve($source, '/base');

    expect($result)->toBe($resolved)
        ->and($httpFetcher->called)->toBeTrue()
        ->and($fileFetcher->called)->toBeFalse();
});

it('routes url with no scheme to file fetcher', function (): void {
    $resolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return null;
        }
    };

    $httpFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'remote content';
        }
    };

    $fileFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'local content';
        }
    };

    $parser = new class($resolved) implements SourceParser
    {
        public function __construct(private readonly ResolvedSource $resolved)
        {
        }

        public function parse(string $content): ResolvedSource
        {
            return $this->resolved;
        }
    };

    $resolver = new DefaultSourceResolver(
        fetchers: [
            'http' => $httpFetcher,
            'https' => $httpFetcher,
            'file' => $fileFetcher,
        ],
        parsers: [SourceType::Openapi->value => $parser],
    );

    $source = new SourceDescription('local', './local/api.json', SourceType::Openapi);
    $resolver->resolve($source, '/base');

    expect($fileFetcher->called)->toBeTrue()
        ->and($httpFetcher->called)->toBeFalse();
});

it('routes https url to https fetcher', function (): void {
    $resolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return null;
        }
    };

    $httpFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'remote content';
        }
    };

    $fileFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'local content';
        }
    };

    $parser = new class($resolved) implements SourceParser
    {
        public function __construct(private readonly ResolvedSource $resolved)
        {
        }

        public function parse(string $content): ResolvedSource
        {
            return $this->resolved;
        }
    };

    $resolver = new DefaultSourceResolver(
        fetchers: [
            'http' => $fileFetcher,
            'https' => $httpFetcher,
            'file' => $fileFetcher,
        ],
        parsers: [SourceType::Openapi->value => $parser],
    );

    $source = new SourceDescription('remote', 'https://example.com/api.json', SourceType::Openapi);
    $resolver->resolve($source, '/base');

    expect($httpFetcher->called)->toBeTrue()
        ->and($fileFetcher->called)->toBeFalse();
});

it('throws SourceParseException when no parser is configured for the source type', function (): void {
    $fetcher = new class() implements SourceFetcher
    {
        public function fetch(string $urlOrPath, string $basePath): string
        {
            return 'content';
        }
    };

    $resolver = new DefaultSourceResolver(
        fetchers: [
            'http' => $fetcher,
            'https' => $fetcher,
            'file' => $fetcher,
        ],
        parsers: [],
    );

    $source = new SourceDescription('test', 'http://example.com/api.json', SourceType::Openapi);

    expect(fn () => $resolver->resolve($source, '/base'))
        ->toThrow(SourceParseException::class, SourceType::Openapi->value);
});

it('throws SourceFetchException for unknown scheme', function (): void {
    $fetcher = new class() implements SourceFetcher
    {
        public function fetch(string $urlOrPath, string $basePath): string
        {
            return 'content';
        }
    };

    $resolver = new DefaultSourceResolver(
        fetchers: [
            'http' => $fetcher,
            'https' => $fetcher,
            'file' => $fetcher,
        ],
        parsers: [],
    );

    $source = new SourceDescription('s3', 's3://my-bucket/api.json', SourceType::Openapi);

    expect(fn () => $resolver->resolve($source, '/base'))
        ->toThrow(SourceFetchException::class, 's3');
});

it('selects the parser by SourceType value, choosing arazzo parser over openapi', function (): void {
    $arazzoResolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return 'arazzo-result';
        }
    };

    $openapiResolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return 'openapi-result';
        }
    };

    $fetcher = new class() implements SourceFetcher
    {
        public function fetch(string $urlOrPath, string $basePath): string
        {
            return 'content';
        }
    };

    $arazzoParser = new class($arazzoResolved) implements SourceParser
    {
        public function __construct(private readonly ResolvedSource $resolved)
        {
        }

        public function parse(string $content): ResolvedSource
        {
            return $this->resolved;
        }
    };

    $openapiParser = new class($openapiResolved) implements SourceParser
    {
        public function __construct(private readonly ResolvedSource $resolved)
        {
        }

        public function parse(string $content): ResolvedSource
        {
            return $this->resolved;
        }
    };

    $resolver = new DefaultSourceResolver(
        fetchers: [
            'http' => $fetcher,
            'https' => $fetcher,
            'file' => $fetcher,
        ],
        parsers: [
            SourceType::Openapi->value => $openapiParser,
            SourceType::Arazzo->value => $arazzoParser,
        ],
    );

    $source = new SourceDescription('wf', 'http://example.com/workflow.arazzo', SourceType::Arazzo);
    $result = $resolver->resolve($source, '/base');

    expect($result)->toBe($arazzoResolved);
});
