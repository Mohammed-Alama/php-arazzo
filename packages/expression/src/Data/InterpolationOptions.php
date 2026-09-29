<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

/**
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
final class InterpolationOptions
{
    /**
     * @var callable|null
     */
    private $stringify;

    public function __construct(
        ?callable $stringify = null,
    ) {
        $this->stringify = $stringify;
    }

    public function getStringify(): ?callable
    {
        return $this->stringify;
    }
}
