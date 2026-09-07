<?php

declare(strict_types=1);
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;

it('declares the single async build entry point', function (): void {
    expect(interface_exists(RunnerGraphBuilderInterface::class))->toBeTrue();
});
