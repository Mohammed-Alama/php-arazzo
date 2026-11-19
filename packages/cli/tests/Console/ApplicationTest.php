<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Console;

use Alama\Arazzo\Cli\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

const CLI_PACKAGE_DIR = __DIR__.'/../../';

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

it('ships an arazzo binary declared in composer.json', function (): void {
    $composer = json_decode((string) file_get_contents(CLI_PACKAGE_DIR.'composer.json'), true);

    $bin = $composer['bin'] ?? [];

    expect(is_array($bin))->toBeTrue()
        ->and(in_array('bin/arazzo', $bin, true))->toBeTrue()
        ->and(file_exists(CLI_PACKAGE_DIR.'bin/arazzo'))->toBeTrue()
        ->and(is_executable(CLI_PACKAGE_DIR.'bin/arazzo'))->toBeTrue();
});

it('prints --version as one line and exits 0', function (): void {
    $app = new Application();
    $app->setAutoExit(false);

    $tester = new ApplicationTester($app);
    $tester->run(['--version' => true], ['interactive' => false]);

    $display = trim($tester->getDisplay());
    $lines = preg_split('/\R/', $display);

    expect(count($lines))->toBe(1)
        ->and($lines[0])->toContain('arazzo')
        ->and($tester->getStatusCode())->toBe(0);
});
