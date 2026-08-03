<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\ExtensionsXPrefixRule;

it('warns on extensions missing x- prefix', function (): void {
    $doc = Fx::doc(extensions: ['x-ok' => 1, 'bad' => 2]);
    $ec = new ErrorCollector();
    $r = new ExtensionsXPrefixRule();
    $r->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->warnings())->toHaveCount(1)->and($r->code())->toBe('extensions.x_prefix');
});
