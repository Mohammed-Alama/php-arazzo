<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Resolver\Exceptions\UnresolvableReferenceException;
use Alama\Arazzo\Resolver\Interfaces\SourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\SourceDocument;

it('registers and resolves sources by name explicitly', function (): void {
    $resolver = new class() implements SourceResolver
    {
        public function resolve(SourceDescription $source, string $basePath): SourceDocument
        {
            return new SourceDocument($source->name, $source->type, 'file://'.$basePath.'/'.$source->url, []);
        }
    };

    $registry = new SourceRegistry($resolver);
    $source = new SourceDescription('test-api', 'api.json', SourceType::Openapi);

    $doc = $registry->resolve($source, '/base');

    expect($doc->name)->toBe('test-api')
        ->and($doc->canonicalUri)->toBe('file:///base/api.json')
        ->and($registry->get('test-api'))->toBe($doc);
});

it('detects circular references during source acquisition', function (): void {
    $registryRef = null;
    $resolver = new class() implements SourceResolver
    {
        public SourceRegistry $registry;

        public function resolve(SourceDescription $source, string $basePath): SourceDocument
        {
            // Trigger a resolution of the SAME source to simulate circular dependency
            $this->registry->resolve(new SourceDescription('test-api', 'api.json', SourceType::Openapi), $basePath);

            return new SourceDocument($source->name, $source->type, 'file://'.$basePath.'/'.$source->url, []);
        }
    };

    $registry = new SourceRegistry($resolver);
    $resolver->registry = $registry;

    $source = new SourceDescription('test-api', 'api.json', SourceType::Openapi);

    expect(fn () => $registry->resolve($source, '/base'))
        ->toThrow(UnresolvableReferenceException::class, "Circular reference detected when resolving source 'test-api'");
});

it('returns null for missing sources', function (): void {
    $resolver = new class() implements SourceResolver
    {
        public function resolve(SourceDescription $source, string $basePath): SourceDocument
        {
            return new SourceDocument($source->name, $source->type, '', []);
        }
    };
    $registry = new SourceRegistry($resolver);

    expect($registry->get('non-existent'))->toBeNull();
});
