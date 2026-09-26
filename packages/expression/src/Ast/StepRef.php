<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class StepRef extends ExpressionAst
{
    public function __construct(public ?string $stepId, public StepPart $part) {}

    public function mapToExpressionReference(): ExpressionReference
    {
        $part = $this->part;

        if ($part instanceof OutputPart) {
            return new ExpressionReference(ReferenceKind::Step, $this->stepId, 'outputs', $part->name, jsonPointer: $part->jsonPointer);
        }

        if ($part instanceof InputPart) {
            return new ExpressionReference(ReferenceKind::Step, $this->stepId, 'inputs', $part->name);
        }

        if ($part instanceof RequestPart) {
            return new ExpressionReference(ReferenceKind::Step, $this->stepId, 'request', $part->headerName, $part->httpPart, jsonPointer: $part->jsonPointer);
        }

        if ($part instanceof ResponsePart) {
            return new ExpressionReference(ReferenceKind::Step, $this->stepId, 'response', $part->headerName, $part->httpPart, jsonPointer: $part->jsonPointer);
        }

        return new ExpressionReference(ReferenceKind::Step, $this->stepId);
    }
}
