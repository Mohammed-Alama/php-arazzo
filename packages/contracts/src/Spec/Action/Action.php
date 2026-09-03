<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Action;

use Alama\Arazzo\Contracts\Spec\Enum\ActionKind;

abstract readonly class Action
{
    public function __construct(
        public string $name,
        public ActionKind $kind,
    ) {}
}
