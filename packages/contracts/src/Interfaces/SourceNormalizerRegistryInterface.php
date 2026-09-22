<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;

interface SourceNormalizerRegistryInterface
{
    public function register(SourceNormalizerInterface $normalizer): void;

    public function get(SourceType $type): ?SourceNormalizerInterface;
}
