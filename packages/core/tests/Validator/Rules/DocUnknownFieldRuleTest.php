<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\DocUnknownFieldRule;

it('skips when rawRoot is null', function (): void {
    $doc = Fx::doc();
    $ec = new ErrorCollector();
    (new DocUnknownFieldRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('skips non-string keys, known keys, x- extensions; escapes ~ and / in JSON Pointer path', function (): void {
    $raw = ['arazzo' => '1.0.0', 'x-ok' => 1, 0 => 'skip', 'bad~key/with' => true];
    $doc = Fx::doc(rawRoot: $raw);
    $ec = new ErrorCollector();
    (new DocUnknownFieldRule(strict: true))->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->path)->toBe('/bad~0key~1with');
});
