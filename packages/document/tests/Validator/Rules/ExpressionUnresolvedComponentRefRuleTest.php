<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedComponentRefRule;

it('flags unknown component type', function (): void {
    $s = Fx::step('s', 'op', params: [
        new Parameter('c', ParameterIn::Header, new Expression('{$components.ghosttype.x}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s])]);
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedComponentRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
