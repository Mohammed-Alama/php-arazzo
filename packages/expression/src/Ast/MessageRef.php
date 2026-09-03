<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

/**
 * 1.1 Message reference: {$message.header.<name>} or {$message.payload[#/ptr]}.
 * Resolved against the current step's received message (response headers/body).
 */
final readonly class MessageRef extends ExpressionAst
{
    public function __construct(
        public string $part,
        public ?string $name = null,
        public ?string $jsonPointer = null,
    ) {}
}
