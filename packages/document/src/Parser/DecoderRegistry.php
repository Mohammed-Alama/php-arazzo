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

    /**
     * @param  array<string, DecoderInterface>  $decoders
     */
    private function __construct(array $decoders)
    {
        $this->decoders = $decoders;
    }

    /**
     * @return array<string, DecoderInterface>
     */
    public function all(): array
    {
        return $this->decoders;
    }

    /**
     * @param  array<string, DecoderInterface>  $decoders
     */
    public static function fromArray(array $decoders): self
    {
        return new self($decoders);
    }
}
