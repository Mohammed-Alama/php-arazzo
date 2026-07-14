<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ExtensionsXPrefixRule;

it('warns on extensions missing x- prefix', function (): void {
    $doc = Fx::doc(extensions: ['x-ok' => 1, 'bad' => 2]);
    $ec = new ErrorCollector();
    $r = new ExtensionsXPrefixRule();
    $r->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->warnings())->toHaveCount(1)->and($r->code())->toBe('extensions.x_prefix');
});
