<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationIdSourceScopedRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('handles null skip, qualified unknown source, qualified known source, and unqualified with single openapi', function (): void {
    $doc = Fx::doc(
        workflows: [
            Fx::wf('w', [
                Fx::step('a', null, 'x#/paths/y', null),
                Fx::step('b', 'ghost.op'),
                Fx::step('c', 'src.op'),
                Fx::step('d', 'op'),
            ]),
        ],
        sources: [
            new SourceDescription('src', '/u', SourceType::Openapi),
            new SourceDescription('src2', '/u2', SourceType::Openapi),
        ],
    );
    $ec = new ErrorCollector();
    (new StepOperationIdSourceScopedRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});
