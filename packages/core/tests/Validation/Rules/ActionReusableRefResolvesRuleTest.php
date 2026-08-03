<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Dto\Action\SuccessEndAction;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Reusable;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rules\ActionReusableRefResolvesRule;

it('skips non-reusable, flags wrong prefix and unresolved, resolves known', function (): void {
    $end = new SuccessEndAction('e', []);
    $wrongPrefix = new Reusable('$components.parameters.x');
    $unresolved = new Reusable('$components.successActions.ghost');
    $resolved = new Reusable('$components.successActions.known');
    $s = Fx::step('s', 'op', onSuccess: [$end, $wrongPrefix, $unresolved, $resolved]);
    $components = new Components([], [], ['known' => new SuccessEndAction('k', [])], []);
    $doc = Fx::doc(workflows: [Fx::wf('w', [$s])], components: $components);
    $ec = new ErrorCollector();
    (new ActionReusableRefResolvesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});
