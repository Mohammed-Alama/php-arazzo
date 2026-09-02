<?php

declare(strict_types=1);

use Alama\Arazzo\Async\WorkerEvents;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunFailedEvent;
use Alama\Arazzo\Events\StepExecutedEvent;
use Alama\Arazzo\Events\StepFailedEvent;
use Alama\Arazzo\Events\StepStartedEvent;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Validator\Data\Error;
use Alama\Arazzo\Validator\Data\ValidationResult;
use Alama\Arazzo\Validator\Exceptions\PreflightFailureException;

function guardDocumentForEvents(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('E', null, null, '1.0.0'),
        sourceDescriptions: [],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function eventsFixture(): array
{
    $dispatcher = new SimpleEventDispatcher();
    $events = new WorkerEvents($dispatcher);

    return [$events, $dispatcher];
}

it('emits typed lifecycle events with timestamps', function (): void {
    $dispatcher = new SimpleEventDispatcher();
    $seen = [];
    foreach ([StepStartedEvent::class, StepExecutedEvent::class, RunCompletedEvent::class] as $class) {
        $dispatcher->subscribe($class, function (object $e) use (&$seen) {
            $seen[] = $e;
        });
    }
    $events = new WorkerEvents($dispatcher);

    $events->stepStarted('e1', 'wf', 's1', 2);
    $events->stepExecuted('e1', 'wf', 's1', 200, ['k' => 'v'], true);
    $events->runCompleted('e1', 'wf', ['out' => 1]);

    expect(count($seen))->toBe(3)
        ->and($seen[0])->toBeInstanceOf(StepStartedEvent::class)
        ->and($seen[0]->attempt)->toBe(2)
        ->and($seen[1]->criteriaMet)->toBeTrue()
        ->and($seen[2]->outputs['out'])->toBe(1);
});

it('keeps step and run failure categories in agreement', function (): void {
    $invalidResult = new ValidationResult(
        guardDocumentForEvents(),
        [new Error('e.code', 'broken', '/x')],
        [],
    );

    $cases = [
        'authoring' => new PreflightFailureException('bad doc', $invalidResult),
        'execution' => new RuntimeException('boom'),
    ];

    foreach ($cases as $expectedCategory => $cause) {
        $dispatcher = new SimpleEventDispatcher();
        $stepFailures = [];
        $runFailures = [];
        $dispatcher->subscribe(StepFailedEvent::class, function (StepFailedEvent $e) use (&$stepFailures) {
            $stepFailures[] = $e;
        });
        $dispatcher->subscribe(RunFailedEvent::class, function (RunFailedEvent $e) use (&$runFailures) {
            $runFailures[] = $e;
        });

        (new WorkerEvents($dispatcher))->failurePair('e9', 'wf', 's9', $cause);

        expect(count($stepFailures))->toBe(1)
            ->and($stepFailures[0]->category)->toBe($expectedCategory)
            ->and(count($runFailures))->toBe(1)
            ->and($runFailures[0]->category)->toBe($expectedCategory);
    }
});
