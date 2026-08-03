<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\ExpressionContextMisuseRule;

it('flags HttpMetaRef in parameters context', function (): void {
    $s = Fx::step('s', 'op', params: [
        new Parameter('c', ParameterIn::Header, new Expression('{$statusCode}')),
    ]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s])]);
    $ec = new ErrorCollector();
    (new ExpressionContextMisuseRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('allows runtime refs in outputs', function (): void {
    $s = Fx::step('s', 'op', outputs: ['o' => new Expression('{$statusCode}')]);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s])]);
    $ec = new ErrorCollector();
    (new ExpressionContextMisuseRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
