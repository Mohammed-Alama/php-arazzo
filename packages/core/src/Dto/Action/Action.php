<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Action;

use Alama\Arazzo\Dto\Enum\ActionKind;

abstract readonly class Action
{
    public function __construct(
        public string $name,
        public ActionKind $kind,
    ) {
    }
}
