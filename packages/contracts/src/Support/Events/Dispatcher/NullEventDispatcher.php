<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Support\Events\Dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @internal stays out of the advertised contract; used internally by the contracts package.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
