<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;

/**
 * Actor-in-the-loop Step target (1.2 proposal, D10).
 */
final readonly class Interaction
{
    public function __construct(
        public mixed $expectedPayload = null,
        public ?string $timeout = null,
        public ?InteractionMode $mode = null,
    ) {}
}
