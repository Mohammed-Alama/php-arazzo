<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\SourceTypeMatchesRule;
use Alama\Arazzo\Expression\SymbolTable;

it('is a no-op with reserved code', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $ec = new ErrorCollector();
    $r = new SourceTypeMatchesRule();
    $r->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([])->and($r->code())->toBe('source.type_matches');
});
