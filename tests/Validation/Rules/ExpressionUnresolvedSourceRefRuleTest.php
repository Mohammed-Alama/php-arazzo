<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Tests\Support\Fx;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\ExpressionUnresolvedSourceRefRule;

it('flags unknown source, accepts declared', function (): void {
    $s = Fx::step('s', 'op', params: [
        new Parameter('a', ParameterIn::Header, new Expression('{$sourceDescriptions.src.url}')),
        new Parameter('b', ParameterIn::Header, new Expression('{$sourceDescriptions.ghost.url}')),
    ]);
    $doc = Fx::doc(
        workflows: [Fx::wf('main', [$s])],
        sources: [new SourceDescription('src', '/u', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new ExpressionUnresolvedSourceRefRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});
