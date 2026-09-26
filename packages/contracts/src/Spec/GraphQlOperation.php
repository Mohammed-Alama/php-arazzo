<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

/**
 * GraphQL operation object (PR #567).
 */
final readonly class GraphQlOperation
{
    /** @var array<string,mixed>|null */
    public ?array $extensions;

    public function __construct(
        public string $schema,
        public string $operation,
        /** @param array<string,mixed>|null $extensions */
        ?iterable $extensions = null,
        public ?Selector $extensionsSelector = null,
    ) {
        // @phpstan-ignore-next-line
        $this->extensions = $extensions;
    }
}
