<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Resolver;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SourceNormalizerRegistry implements SourceNormalizerRegistryInterface
{
    /** @var list<SourceNormalizerInterface> */
    private array $normalizers = [];

    public function register(SourceNormalizerInterface $normalizer): void
    {
        $this->normalizers[] = $normalizer;
    }

    public function get(SourceType $type): ?SourceNormalizerInterface
    {
        foreach ($this->ordered() as $normalizer) {
            if ($normalizer->supports($type)) {
                return $normalizer;
            }
        }

        return null;
    }

    /**
     * Highest priority first; stable so registration order breaks ties.
     *
     * @return list<SourceNormalizerInterface>
     */
    private function ordered(): array
    {
        $normalizers = $this->normalizers;
        usort(
            $normalizers,
            static fn (SourceNormalizerInterface $a, SourceNormalizerInterface $b): int => $b->priority() <=> $a->priority(),
        );

        return $normalizers;
    }
}
