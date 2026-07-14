<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\Action\SuccessEndAction;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ActionReusableRefResolvesRule;

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
