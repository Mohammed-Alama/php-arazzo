<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepOutputsUniqueRule;

it('is a no-op with reserved code', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $ec = new ErrorCollector();
    $r = new StepOutputsUniqueRule();
    $r->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([])->and($r->code())->toBe('step.outputs_unique');
});
