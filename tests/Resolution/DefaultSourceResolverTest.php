<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution;

use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Resolution\SourceFetcher;
use Alama\LaravelArazzo\Resolution\SourceParser;

it('resolves http url using remote fetcher and the matching parser', function (): void {
    $resolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return 'extracted';
        }
    };

    $remoteFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'remote content';
        }
    };

    $localFetcher = new class() implements SourceFetcher
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
        remoteFetcher: $remoteFetcher,
        localFetcher: $localFetcher,
        parsers: [SourceType::Openapi->value => $parser],
    );

    $source = new SourceDescription('test', 'http://example.com/api.json', SourceType::Openapi);
    $result = $resolver->resolve($source, '/base');

    expect($result)->toBe($resolved)
        ->and($remoteFetcher->called)->toBeTrue()
        ->and($localFetcher->called)->toBeFalse();
});

it('routes non-http url to local fetcher', function (): void {
    $resolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return null;
        }
    };

    $remoteFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'remote content';
        }
    };

    $localFetcher = new class() implements SourceFetcher
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
        remoteFetcher: $remoteFetcher,
        localFetcher: $localFetcher,
        parsers: [SourceType::Openapi->value => $parser],
    );

    $source = new SourceDescription('local', './local/api.json', SourceType::Openapi);
    $resolver->resolve($source, '/base');

    expect($localFetcher->called)->toBeTrue()
        ->and($remoteFetcher->called)->toBeFalse();
});

it('routes https url to remote fetcher', function (): void {
    $resolved = new class() implements ResolvedSource
    {
        public function extract(string $jsonPointer): mixed
        {
            return null;
        }
    };

    $remoteFetcher = new class() implements SourceFetcher
    {
        public bool $called = false;

        public function fetch(string $urlOrPath, string $basePath): string
        {
            $this->called = true;

            return 'remote content';
        }
    };

    $localFetcher = new class() implements SourceFetcher
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
        remoteFetcher: $remoteFetcher,
        localFetcher: $localFetcher,
        parsers: [SourceType::Openapi->value => $parser],
    );

    $source = new SourceDescription('remote', 'https://example.com/api.json', SourceType::Openapi);
    $resolver->resolve($source, '/base');

    expect($remoteFetcher->called)->toBeTrue()
        ->and($localFetcher->called)->toBeFalse();
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
        remoteFetcher: $fetcher,
        localFetcher: $fetcher,
        parsers: [],
    );

    $source = new SourceDescription('test', 'http://example.com/api.json', SourceType::Openapi);

    expect(fn () => $resolver->resolve($source, '/base'))
        ->toThrow(SourceParseException::class, SourceType::Openapi->value);
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
        remoteFetcher: $fetcher,
        localFetcher: $fetcher,
        parsers: [
            SourceType::Openapi->value => $openapiParser,
            SourceType::Arazzo->value => $arazzoParser,
        ],
    );

    $source = new SourceDescription('wf', 'http://example.com/workflow.arazzo', SourceType::Arazzo);
    $result = $resolver->resolve($source, '/base');

    expect($result)->toBe($arazzoResolved);
});
