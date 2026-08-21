<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\SourceDocument;
use Alama\Arazzo\Resolver\Exceptions\UnresolvableReferenceException;

final class SourceRegistry
{
    /** @var array<string, SourceDocument> */
    private array $documents = [];

    /** @var array<string, true> */
    private array $resolving = [];

    public function __construct(
        private readonly SourceResolver $resolver,
        private readonly string $basePath,
    ) {
    }

    public function register(SourceDocument $document): void
    {
        $this->documents[$document->name] = $document;
    }

    public function get(string $name): ?SourceDocument
    {
        return $this->documents[$name] ?? null;
    }

    public function resolve(SourceDescription $source): SourceDocument
    {
        if (isset($this->documents[$source->name])) {
            return $this->documents[$source->name];
        }

        if (isset($this->resolving[$source->name])) {
            throw new UnresolvableReferenceException("Circular reference detected when resolving source '{$source->name}'");
        }

        $this->resolving[$source->name] = true;

        try {
            $document = $this->resolver->resolve($source, $this->basePath);
            $this->register($document);

            return $document;
        } finally {
            unset($this->resolving[$source->name]);
        }
    }
}
