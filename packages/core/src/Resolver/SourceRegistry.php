<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

use Alama\Arazzo\Resolver\Exceptions\UnresolvableReferenceException;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\SourceDocument;

final class SourceRegistry implements SourceResolver
{
    /** @var array<string, SourceDocument> */
    private array $documents = [];

    /** @var array<string, true> */
    private array $resolving = [];

    public function __construct(
        private readonly SourceResolver $resolver,
    ) {
    }

    public function get(string $name): ?SourceDocument
    {
        return $this->documents[$name] ?? null;
    }

    public function resolve(SourceDescription $source, string $basePath): SourceDocument
    {
        if (isset($this->documents[$source->name])) {
            return $this->documents[$source->name];
        }

        if (isset($this->resolving[$source->name])) {
            throw new UnresolvableReferenceException("Circular reference detected when resolving source '{$source->name}'");
        }

        $this->resolving[$source->name] = true;

        try {
            $document = $this->resolver->resolve($source, $basePath);
            $this->register($document);

            return $document;
        } finally {
            unset($this->resolving[$source->name]);
        }
    }

    public function register(SourceDocument $document): void
    {
        $this->documents[$document->name] = $document;
    }
}
