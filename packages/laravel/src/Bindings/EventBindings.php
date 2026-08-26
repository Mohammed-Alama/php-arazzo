<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Support\Events\Listener\LedgerAppendingListener;
use Illuminate\Contracts\Container\Container;
use Psr\EventDispatcher\EventDispatcherInterface;

/** PSR-14 event bus: in-memory dispatcher bridged to the durable ledger. */
final class EventBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(SimpleEventDispatcher::class, function (Container $app) {
            $dispatcher = new SimpleEventDispatcher();

            if ($app->bound(EventLedgerInterface::class)) {
                LedgerAppendingListener::registerAll(
                    $dispatcher,
                    $app->make(EventLedgerInterface::class),
                );
            }

            return $dispatcher;
        });

        $app->bindIf(
            EventDispatcherInterface::class,
            fn (Container $app) => $app->make(SimpleEventDispatcher::class),
        );
    }
}
