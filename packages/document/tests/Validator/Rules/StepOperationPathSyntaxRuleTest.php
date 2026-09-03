<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationPathSyntaxRule;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Tests\Support\Fx;

it('flags missing hash, unknown source, empty pointer, and non-slash pointer', function (): void {
    $doc = Fx::doc(
        workflows: [
            Fx::wf('w', [
                Fx::step('a', null, 'no-hash'),
                Fx::step('b', null, 'ghost#/x'),
                Fx::step('c', null, 'src#'),
                Fx::step('d', null, 'src#nolead'),
                Fx::step('e', null, null, null),
            ]),
        ],
        sources: [new SourceDescription('src', '/u', SourceType::Openapi)],
    );
    $ec = new ErrorCollector();
    (new StepOperationPathSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBeGreaterThanOrEqual(4);
});
