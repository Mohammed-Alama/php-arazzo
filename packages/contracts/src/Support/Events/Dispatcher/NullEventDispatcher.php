<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Support\Events\Dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
