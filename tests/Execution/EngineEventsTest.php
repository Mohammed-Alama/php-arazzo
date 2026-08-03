<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\WorkflowContext;

class EngineEventsNoopQueue implements \Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface {
    public array $dispatched = [];
    public function dispatch(object $job, int $delaySeconds = 0): void { $this->dispatched[] = $job; }
}
class EngineEventsNoopStateStore implements \Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface {
    public function load(string $id): ?array { return null; }
    public function save(string $id, array $state, ?int $ttlSeconds = null): void {}
}

it('dispatches RunStarted on first evaluate per executionId', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $ctx = new WorkflowContext('def', [], [], [], 'w', 'exec-1');
    $d = new SimpleEventDispatcher();

    $captured = [];
    $d->subscribe(RunStarted::class, function ($e) use (&$captured) { $captured[] = $e->executionId; });

    $engine = new Engine(new EngineEventsNoopQueue(), new EngineEventsNoopStateStore(), $d);
    $engine->evaluate($wf, $ctx);
    $engine->evaluate($wf, $ctx); // second call: should NOT re-fire RunStarted

    expect($captured)->toBe(['exec-1']);
});
