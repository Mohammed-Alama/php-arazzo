<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\RequestBody;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\StepRequestBodyReplacementsTargetRule;

it('skips steps without body and flags empty/non-slash targets', function (): void {
    $doc = Fx::doc(workflows: [
        Fx::wf('w', [
            Fx::step('a', 'op', body: null),
            Fx::step('b', 'op', body: new RequestBody(null, null, [
                new PayloadReplacement('', 'v'),
                new PayloadReplacement('/ok', 'v'),
            ])),
        ]),
    ]);
    $ec = new ErrorCollector();
    (new StepRequestBodyReplacementsTargetRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
