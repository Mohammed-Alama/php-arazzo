<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;

abstract readonly class Action
{
    public function __construct(
        public string $name,
        public ActionKind $kind,
    ) {
    }
}
