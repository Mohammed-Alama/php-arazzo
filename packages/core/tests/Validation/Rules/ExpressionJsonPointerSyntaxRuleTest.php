<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\RequestBody;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\ExpressionJsonPointerSyntaxRule;

it('flags bad pointer segment in request part; accepts valid response pointer', function (): void {
    $bodyOk = new RequestBody(null, new Expression('{$steps.a.request.body#/x}'), []);
    $s = Fx::step('a', 'op', body: $bodyOk, outputs: [
        'ok' => new Expression('{$steps.a.response.body#/ok}'),
    ]);
    $bodyBad = new RequestBody(null, new Expression('{$steps.a.request.body#/bad~9}'), []);
    $s2 = Fx::step('b', 'op', body: $bodyBad);
    $doc = Fx::doc(workflows: [Fx::wf('main', [$s, $s2])]);
    $ec = new ErrorCollector();
    (new ExpressionJsonPointerSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
