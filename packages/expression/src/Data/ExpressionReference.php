<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * Public projection of a parsed expression root.
 *
 * Cross-seam value type: the `ExpressionEngineInterface` hands these to
 * downstream consumers so they can inspect what an expression references
 * without depending on the (internal) expression AST.
 *
 * Field semantics depend on {@see $kind}:
 *
 * - `Input`/`Output`: {@see $target} is the input/output name,
 *   {@see $jsonPointer} an optional JSON-pointer suffix.
 * - `Step`: {@see $target} is the step id (null = the current step),
 *   {@see $part} one of `outputs|inputs|request|response`.
 *   For `request`/`response` parts {@see $httpPart} (`url|method|statusCode|
 *   body|header|query|path`) and {@see $name} (header name) qualify further;
 *   {@see $jsonPointer} is the body pointer.
 * - `Workflow`: {@see $target} is the workflow id, {@see $part} the
 *   `inputs|outputs` member bag, {@see $name} the member name.
 * - `Source`: {@see $target} is the source description name, {@see $name}
 *   the raw tail (`url`, `type`, ...).
 * - `Component`: {@see $target} is the component type, {@see $name} the name.
 * - `Message`: {@see $part} is `header|payload`, {@see $name} the header name,
 *   {@see $jsonPointer} the payload pointer.
 * - `HttpMeta`: {@see $httpPart} is `url|method|statusCode`.
 * - `Self`: no fields.
 */
final readonly class ExpressionReference
{
    public function __construct(
        public ReferenceKind $kind,
        public ?string $target = null,
        public ?string $part = null,
        public ?string $name = null,
        public ?string $httpPart = null,
        public ?string $jsonPointer = null,
    ) {}
}
