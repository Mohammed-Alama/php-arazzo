<?php

declare(strict_types=1);

namespace Alama\Arazzo\Events\Dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

final class SimpleEventDispatcher implements EventDispatcherInterface, ListenerProviderInterface
{
    /** @var array<class-string, list<callable>> */
    private array $listeners = [];

    /**
     * @param class-string $eventClass
     */
    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
            $listener($event);
        }

        return $event;
    }

    /** @return iterable<callable> */
    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->listeners as $class => $callables) {
            if ($event instanceof $class) {
                yield from $callables;
            }
        }
    }
}
