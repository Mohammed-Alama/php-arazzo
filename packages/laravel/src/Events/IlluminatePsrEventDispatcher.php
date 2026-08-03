<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Events;

use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

final class IlluminatePsrEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private IlluminateDispatcher $dispatcher)
    {
    }

    public function dispatch(object $event): object
    {
        $this->dispatcher->dispatch($event);

        return $event;
    }
}
