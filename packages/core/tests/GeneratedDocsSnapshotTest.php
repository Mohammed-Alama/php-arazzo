<?php

declare(strict_types=1);
use ArazzoDocs\ScannedFile;

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Scanner.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/NamespaceGraphDoc.php';

it('keys scans by package so State does not collide', function (): void {
    expect(\ArazzoDocs\packageKey('contracts', 'State'))->toBe('contracts:State')
        ->and(\ArazzoDocs\packageKey('runner', 'State'))->toBe('runner:State');
});

it('labels Ast from its real namespace', function (): void {
    expect(\ArazzoDocs\moduleLabel('expression', 'Ast', 'Alama\\Arazzo\\Expression\\Ast'))
        ->toBe('Alama\\Arazzo\\Expression\\Ast');
});

it('keeps same-named modules from different packages separate', function (): void {
    $scans = [
        'contracts' => ['State' => [new ScannedFile(
            path: 'State',
            relativeDir: 'State',
            namespace: 'Alama\\Arazzo\\Contracts\\State',
            className: 'WorkflowContext',
            isInterface: false,
            uses: [],
            useStatements: [],
            content: '',
            package: 'contracts',
        )]],
        'runner' => ['State' => [new ScannedFile(
            path: 'State',
            relativeDir: 'State',
            namespace: 'Alama\\Arazzo\\Runner\\State',
            className: 'WorkflowState',
            isInterface: false,
            uses: ['Alama\\Arazzo\\Contracts\\State\\WorkflowContext'],
            useStatements: [],
            content: '',
            package: 'runner',
        )]],
    ];

    $out = \ArazzoDocs\NamespaceGraphDoc\render($scans);

    expect($out)->toContain('runner_State --> contracts_State')
        ->and($out)->toContain('Alama\\Arazzo\\Contracts\\State')
        ->and($out)->toContain('Alama\\Arazzo\\Runner\\State');
});
