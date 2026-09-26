<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources\Normalizer;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerInterface;
use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

/**
 * Registry for source normalizers.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SourceNormalizerRegistry implements SourceNormalizerRegistryInterface
{
    /** @var array<string, SourceNormalizerInterface> */
    private array $normalizers = [];

    public function register(SourceNormalizerInterface $normalizer): void
    {
        // For now, we only support OpenAPI normalizers
        $this->normalizers['openapi'] = $normalizer;
    }

    public function get(SourceType $type): ?SourceNormalizerInterface
    {
        $key = $type->value;
        error_log("Looking up normalizer for key: '$key'");

        return $this->normalizers[$key] ?? null;
    }
}
