<?php

declare(strict_types=1);

use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;

it('returns the event unchanged', function () {
    $event = new stdClass();
    expect((new NullEventDispatcher())->dispatch($event))->toBe($event);
});
