<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser;

use Alama\Arazzo\Document\Parser\Interfaces\DecoderInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class DecoderRegistry
{
    /**
     * @var array<string, DecoderInterface>
     */
    private readonly array $decoders;

    /** @phpstan-ignore missingType.iterableValue,assign.propertyType */
    private function __construct(array $decoders)
    {
        /** @phpstan-ignore assign.propertyType */
        $this->decoders = $decoders;
    }

    /** @phpstan-ignore missingType.iterableValue,return.type */
    public function all(): array
    {
        return $this->decoders;
    }

    /** @phpstan-ignore missingType.iterableValue */
    public static function fromArray(array $decoders): self
    {
        return new self($decoders);
    }
}
