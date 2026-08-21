<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Context\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Events\RunStarted;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Runner\Execution\Engine;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;

class EngineEventsNoopQueue implements QueueDriverInterface
{
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = $job;
    }
}
class EngineEventsNoopStateStore implements StateStoreInterface
{
    public function load(string $id): ?array
    {
        return null;
    }

    public function save(string $id, array $state, ?int $ttlSeconds = null): void
    {
    }
}

it('dispatches RunStarted on first evaluate per executionId', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $ctx = new WorkflowContext('def', [], [], [], 'w', 'exec-1');
    $d = new SimpleEventDispatcher();

    $captured = [];
    $d->subscribe(RunStarted::class, function ($e) use (&$captured) {
        $captured[] = $e->executionId;
    });

    $engine = new Engine(new EngineEventsNoopQueue(), new EngineEventsNoopStateStore(), $d);
    $engine->evaluate($wf, $ctx);
    $engine->evaluate($wf, $ctx); // second call: should NOT re-fire RunStarted

    expect($captured)->toBe(['exec-1']);
});
