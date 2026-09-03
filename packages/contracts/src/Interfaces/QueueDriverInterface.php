<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

interface QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void;
}
