<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Scanner.php';

it('keys scans by package so State does not collide', function (): void {
    expect(\ArazzoDocs\packageKey('contracts', 'State'))->toBe('contracts:State')
        ->and(\ArazzoDocs\packageKey('runner', 'State'))->toBe('runner:State');
});

it('labels Ast from its real namespace', function (): void {
    expect(\ArazzoDocs\moduleLabel('expression', 'Ast', 'Alama\\Arazzo\\Expression\\Ast'))
        ->toBe('Alama\\Arazzo\\Expression\\Ast');
});
