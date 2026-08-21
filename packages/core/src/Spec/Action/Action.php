<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Action;

use Alama\Arazzo\Spec\Enum\ActionKind;

abstract readonly class Action
{
    public function __construct(
        public string $name,
        public ActionKind $kind,
    ) {
    }
}
