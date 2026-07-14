<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class OutputPart extends StepPart
{
    public function __construct(public string $name) {}
}
