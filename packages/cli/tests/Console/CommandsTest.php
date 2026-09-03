<?php

declare(strict_types=1);

use Alama\Arazzo\Cli\Console\Command\ExplainCommand;
use Alama\Arazzo\Cli\Console\Command\ListWorkflowsCommand;
use Alama\Arazzo\Cli\Console\Command\ValidateCommand;
use Symfony\Component\Console\Tester\CommandTester;

const FIXTURE_MINIMAL = __DIR__.'/../fixtures/loader/minimal.yaml';

function commandTester(string $class): CommandTester
{
    return new CommandTester(new $class());
}

it('validate exits 0 and prints success for a valid document', function (): void {
    $tester = commandTester(ValidateCommand::class);
    $tester->execute(['file' => FIXTURE_MINIMAL]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('valid');
});

it('validate exits 1 with coded errors for a broken document', function (): void {
    $tmp = sys_get_temp_dir().'/arazzo-cli-broken-'.uniqid().'.yaml';
    file_put_contents($tmp, "arazzo: \"1.0.0\"\ninfo:\n  title: x\n  version: \"1\"\nsourceDescriptions: []\nworkflows: []\n");

    $tester = commandTester(ValidateCommand::class);
    $tester->execute(['file' => $tmp]);

    unlink($tmp);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('invalid');
});

it('list-workflows prints workflow ids and step targets', function (): void {
    $tester = commandTester(ListWorkflowsCommand::class);
    $tester->execute(['file' => FIXTURE_MINIMAL]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('wf')
        ->and($tester->getDisplay())->toContain('s1');
});

it('explain prints the topological execution order', function (): void {
    $tester = commandTester(ExplainCommand::class);
    $tester->execute(['file' => FIXTURE_MINIMAL]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('execution order')
        ->and($tester->getDisplay())->toContain('s1');
});
