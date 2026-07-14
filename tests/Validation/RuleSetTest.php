<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\RuleSet;

class DummyRule implements Rule
{
    public function __construct(private readonly string $c)
    {
    }

    public function code(): string
    {
        return $this->c;
    }

    public function check(ArazzoDocument $d, SymbolTable $s, ErrorCollector $e): void
    {
        $e->error($this->c, 'boom', '/');
    }
}

it('is immutable — withRule returns new instance', function (): void {
    $a = new RuleSet([]);
    $b = $a->withRule(new DummyRule('x'));
    expect($a->rules())->toBe([])
        ->and($b->rules())->toHaveCount(1);
});

it('honours disabled list', function (): void {
    $set = RuleSet::default(disabled: ['x'], strict: false)
        ->withRule(new DummyRule('x'))
        ->withRule(new DummyRule('y'));
    $codes = array_map(fn (Rule $r) => $r->code(), $set->activeRules());
    expect($codes)->toBe(['y']);
});
