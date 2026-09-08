<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class WorkflowRef extends ExpressionAst
{
    /**
     * @param  'inputs'|'outputs'  $partKind
     */
    public function __construct(
        public string $workflowId,
        public string $partKind,
        public string $name,
    ) {}
}
