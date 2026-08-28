<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\Enum\SourceType;
use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Expression\SourceDescription;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\ExpressionUnresolvedSourceRefRule;

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
