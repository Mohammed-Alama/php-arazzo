<?php

declare(strict_types=1);

use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Events\RunStarted;
use Alama\Arazzo\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Execution\Contracts\StateStoreInterface;
use Alama\Arazzo\Execution\Engine;
use Alama\Arazzo\Execution\WorkflowContext;

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
