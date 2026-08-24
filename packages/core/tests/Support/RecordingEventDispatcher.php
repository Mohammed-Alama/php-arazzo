<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /** @return list<class-string> */
    public function eventClasses(): array
    {
        return array_map(fn (object $event): string => $event::class, $this->events);
    }
}
