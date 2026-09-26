<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolver;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Sources\Resolver\SourceNormalizerRegistry;

final class FakeNormalizer implements SourceNormalizerInterface
{
    /** @param list<SourceType> $types */
    public function __construct(
        private readonly string $fakeName,
        private readonly int $fakePriority,
        private readonly array $types,
    ) {}

    public function name(): string
    {
        return $this->fakeName;
    }

    public function priority(): int
    {
        return $this->fakePriority;
    }

    public function supports(SourceType $type): bool
    {
        return in_array($type, $this->types, true);
    }

    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array
    {
        return [];
    }
}

it('returns null when no normalizer supports the requested source type', function (): void {
    $registry = new SourceNormalizerRegistry();
    $openapi = new FakeNormalizer('openapi', 0, [SourceType::Openapi]);
    $registry->register($openapi);

    expect($registry->get(SourceType::Openapi))->toBe($openapi);
    expect($registry->get(SourceType::Asyncapi))->toBeNull();
});

it('returns the first registered normalizer that supports the requested type', function (): void {
    $registry = new SourceNormalizerRegistry();
    $openapi = new FakeNormalizer('openapi', 0, [SourceType::Openapi]);
    $asyncapi = new FakeNormalizer('asyncapi', 0, [SourceType::Asyncapi]);
    $registry->register($openapi);
    $registry->register($asyncapi);

    expect($registry->get(SourceType::Openapi))->toBe($openapi);
    expect($registry->get(SourceType::Asyncapi))->toBe($asyncapi);
});

it('prefers the highest-priority normalizer, then registration order for ties', function (): void {
    $registry = new SourceNormalizerRegistry();
    $lenient = new FakeNormalizer('openapi-lenient', 0, [SourceType::Openapi]);
    $strict = new FakeNormalizer('openapi-strict', 100, [SourceType::Openapi]);
    $registry->register($lenient);
    $registry->register($strict);

    expect($registry->get(SourceType::Openapi))->toBe($strict);

    $registry2 = new SourceNormalizerRegistry();
    $first = new FakeNormalizer('first', 0, [SourceType::Openapi]);
    $second = new FakeNormalizer('second', 0, [SourceType::Openapi]);
    $registry2->register($first);
    $registry2->register($second);

    expect($registry2->get(SourceType::Openapi))->toBe($first);
});
