<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\DocUnknownFieldRule;
use Alama\Arazzo\Tests\Support\Fx;

it('skips when rawRoot is null', function (): void {
    $doc = Fx::doc();
    $ec = new ErrorCollector();
    (new DocUnknownFieldRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('accepts known keys and x- extensions; escapes ~ and / in JSON Pointer path', function (): void {
    $raw = ['arazzo' => '1.0.0', 'x-ok' => 1, 'bad~key/with' => true];
    $doc = Fx::doc(rawRoot: $raw);
    $ec = new ErrorCollector();
    (new DocUnknownFieldRule(strict: true))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->path)->toBe('/bad~0key~1with');
});

it('reports a numeric top-level key as an unknown field', function (): void {
    // JSON object keys that look numeric are decoded to int keys by PHP.
    $doc = Fx::doc(rawRoot: ['0' => 'x']);
    $ec = new ErrorCollector();
    (new DocUnknownFieldRule(strict: true))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->path)->toBe('/0');
});
