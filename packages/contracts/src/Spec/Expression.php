<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

final readonly class Expression
{
    public function __construct(public string $raw) {}
}
