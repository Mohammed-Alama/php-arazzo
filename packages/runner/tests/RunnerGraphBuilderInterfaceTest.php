<?php

declare(strict_types=1);
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;

it('declares the async graph assembly entry points', function (): void {
    expect(interface_exists(RunnerGraphBuilderInterface::class))->toBeTrue();
    expect(method_exists(RunnerGraphBuilderInterface::class, 'buildAsync'))->toBeTrue();
    expect(method_exists(RunnerGraphBuilderInterface::class, 'buildWorker'))->toBeTrue();
});
