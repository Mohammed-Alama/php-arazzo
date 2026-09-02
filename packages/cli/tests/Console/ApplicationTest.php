<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Console;

use Alama\Arazzo\Console\Application;

it('sets the application name and version', function (): void {
    $app = new Application();

    expect($app->getName())->toBe('arazzo')
        ->and($app->getVersion())->toBe('1.0.0-alpha');
});

it('registers the five arazzo console commands', function (): void {
    $app = new Application();

    $commands = array_keys($app->all());

    foreach (['validate', 'list-workflows', 'explain', 'run', 'render'] as $name) {
        expect($commands)->toContain($name);
    }
});

it('adds the working-dir global option', function (): void {
    $app = new Application();

    $def = $app->getDefinition();

    expect($def->hasOption('working-dir'))->toBeTrue()
        ->and($def->getOption('working-dir')->getShortcut())->toBe('d');
});
