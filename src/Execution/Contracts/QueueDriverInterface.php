<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;

interface QueueDriverInterface
{
    public function dispatch(object $job, int $delaySeconds = 0): void;
}
