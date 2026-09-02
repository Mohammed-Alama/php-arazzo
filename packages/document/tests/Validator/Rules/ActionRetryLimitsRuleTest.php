<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ActionRetryLimitsRule;

it('skips non-retry actions, accepts positive, flags negative retryAfter/retryLimit', function (): void {
    $notRetry = new SuccessEndAction('e', []);
    $ok = new RetryAction('r1', 1, 1, null, null, []);
    $badAfter = new RetryAction('r2', -1, 1, null, null, []);
    $badLimit = new RetryAction('r3', 1, -1, null, null, []);
    $s = Fx::step('s', 'op', onFailure: [$notRetry, $ok, $badAfter, $badLimit]);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])]);
    $ec = new ErrorCollector();
    (new ActionRetryLimitsRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});
