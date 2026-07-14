<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class StepRef extends ExpressionAst
{
    public function __construct(public string $stepId, public StepPart $part)
    {
    }
}
