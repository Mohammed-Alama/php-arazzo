<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * 1.1 Message reference: {$message.header.<name>} or {$message.payload[#/ptr]}.
 * Resolved against the current step's received message (response headers/body).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class MessageRef extends ExpressionAst
{
    public function __construct(
        public string $part,
        public ?string $name = null,
        public ?string $jsonPointer = null,
    ) {}

    public function mapToExpressionReference(): ExpressionReference
    {
        return new ExpressionReference(
            ReferenceKind::Message,
            part: $this->part,
            name: $this->name,
            jsonPointer: $this->jsonPointer,
        );
    }
}
