<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Async\WorkerEvents;
use Alama\Arazzo\Runner\Events\RunCompleted;
use Alama\Arazzo\Runner\Events\RunFailed;
use Alama\Arazzo\Runner\Events\StepExecuted;
use Alama\Arazzo\Runner\Events\StepFailed;
use Alama\Arazzo\Runner\Events\StepStarted;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Validator\Error;
use Alama\Arazzo\Validator\PreflightFailureException;
use Alama\Arazzo\Validator\ValidationResult;

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
    foreach ([StepStarted::class, StepExecuted::class, RunCompleted::class] as $class) {
        $dispatcher->subscribe($class, function (object $e) use (&$seen) {
            $seen[] = $e;
        });
    }
    $events = new WorkerEvents($dispatcher);

    $events->stepStarted('e1', 'wf', 's1', 2);
    $events->stepExecuted('e1', 'wf', 's1', 200, ['k' => 'v'], true);
    $events->runCompleted('e1', 'wf', ['out' => 1]);

    expect(count($seen))->toBe(3)
        ->and($seen[0])->toBeInstanceOf(StepStarted::class)
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
        $dispatcher->subscribe(StepFailed::class, function (StepFailed $e) use (&$stepFailures) {
            $stepFailures[] = $e;
        });
        $dispatcher->subscribe(RunFailed::class, function (RunFailed $e) use (&$runFailures) {
            $runFailures[] = $e;
        });

        (new WorkerEvents($dispatcher))->failurePair('e9', 'wf', 's9', $cause);

        expect(count($stepFailures))->toBe(1)
            ->and($stepFailures[0]->category)->toBe($expectedCategory)
            ->and(count($runFailures))->toBe(1)
            ->and($runFailures[0]->category)->toBe($expectedCategory);
    }
});
