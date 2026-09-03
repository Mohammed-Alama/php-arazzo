<?php

declare(strict_types=1);

use Alama\Arazzo\Cli\Console\Command\RenderCommand;
use Symfony\Component\Console\Tester\CommandTester;

const RENDER_FIXTURE = __DIR__.'/../../fixtures/loader/minimal.yaml';

function renderTester(): CommandTester
{
    return new CommandTester(new RenderCommand());
}

it('renders markdown by default', function (): void {
    $tester = renderTester();
    $tester->execute(['file' => RENDER_FIXTURE]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('wf');
});

it('renders markdown when format is markdown', function (): void {
    $tester = renderTester();
    $tester->execute(['file' => RENDER_FIXTURE, '--format' => 'markdown']);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('wf');
});

it('renders a mermaid flowchart when format is mermaid', function (): void {
    $tester = renderTester();
    $tester->execute(['file' => RENDER_FIXTURE, '--format' => 'mermaid']);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('flowchart TD');
});

it('accepts the md and mmd format aliases', function (): void {
    $markdown = renderTester();
    $markdown->execute(['file' => RENDER_FIXTURE, '--format' => 'md']);
    expect($markdown->getStatusCode())->toBe(0)
        ->and($markdown->getDisplay())->toContain('wf');

    $mermaid = renderTester();
    $mermaid->execute(['file' => RENDER_FIXTURE, '--format' => 'mmd']);
    expect($mermaid->getStatusCode())->toBe(0)
        ->and($mermaid->getDisplay())->toContain('flowchart TD');
});

it('fails with an explicit message for an unknown format', function (): void {
    $tester = renderTester();
    $tester->execute(['file' => RENDER_FIXTURE, '--format' => 'pdf']);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('unknown format');
});

it('writes rendered output to a file when an output path is given', function (): void {
    $out = sys_get_temp_dir().'/arazzo-render-'.uniqid().'.md';

    try {
        $tester = renderTester();
        $tester->execute(['file' => RENDER_FIXTURE, '--output' => $out]);

        expect($tester->getStatusCode())->toBe(0)
            ->and($tester->getDisplay())->toContain('written')
            ->and(file_get_contents($out))->toContain('wf');
    } finally {
        @unlink($out);
    }
});
