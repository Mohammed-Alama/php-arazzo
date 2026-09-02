<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepTimeoutRequires11Rule;

/**
 * @param  int|null  $timeout  timeout in milliseconds
 * @param  '1.0.0'|'1.1.0'  $version
 */
function timeoutDoc(?int $timeout, string $version = '1.1.0'): ArazzoDocument
{
    $step = new Step('s', null, 'op', null, null, [], null, [], [], [], [], timeout: $timeout);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    return new ArazzoDocument(
        $version,
        new Info('T', null, null, '1'),
        [],
        [$wf],
        new Components([], [], [], []),
        [],
        null,
        SpecVersion::fromRaw($version),
    );
}

it('accepts a positive timeout on a 1.1 document', function (): void {
    $doc = timeoutDoc(1000);
    $ec = new ErrorCollector();
    (new StepTimeoutRequires11Rule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('rejects a timeout on a 1.0 document', function (): void {
    $doc = timeoutDoc(1000, '1.0.0');
    $ec = new ErrorCollector();
    (new StepTimeoutRequires11Rule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->code)->toBe('step.timeout_requires_11')
        ->and($ec->errors()[0]->message)->toContain('requires Arazzo 1.1.0');
});

it('rejects a zero timeout as non-positive', function (): void {
    $doc = timeoutDoc(0);
    $ec = new ErrorCollector();
    (new StepTimeoutRequires11Rule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)
        ->and($ec->errors()[0]->message)->toContain('must be a positive number');
});

it('rejects a negative timeout as non-positive', function (): void {
    $doc = timeoutDoc(-5);
    $ec = new ErrorCollector();
    (new StepTimeoutRequires11Rule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags both version and positivity problems on a 1.0 document', function (): void {
    $doc = timeoutDoc(0, '1.0.0');
    $ec = new ErrorCollector();
    (new StepTimeoutRequires11Rule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});

it('accepts a step without a timeout regardless of version', function (): void {
    $doc = timeoutDoc(null, '1.0.0');
    $ec = new ErrorCollector();
    (new StepTimeoutRequires11Rule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
