<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class OutputPart extends StepPart
{
    /**
     * @param  ?string  $name  null means "all outputs of the step", the
     *                         selector-payload form `$steps.<stepId>.outputs`; a string names one
     *                         output, e.g. `$steps.<stepId>.outputs.<name>`.
     */
    public function __construct(
        public ?string $name,
        public ?string $jsonPointer = null,
    ) {}
}
