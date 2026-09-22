<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;

interface SourceNormalizerInterface extends PluginInterface
{
    public function supports(SourceType $type): bool;

    /**
     * Normalize a raw source document into an operation index.
     *
     * @return array<string, mixed>
     */
    public function normalize(SourceDescription $source, string $rawContent, ?ArazzoDocument $document = null): array;
}
