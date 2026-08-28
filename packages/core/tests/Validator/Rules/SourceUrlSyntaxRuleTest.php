<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\Enum\SourceType;
use Alama\Arazzo\Expression\SourceDescription;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\SourceUrlSyntaxRule;

it('flags empty and whitespace URLs, accepts good ones', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [
        new SourceDescription('a', '', SourceType::Openapi),
        new SourceDescription('b', 'bad url', SourceType::Openapi),
        new SourceDescription('c', 'ok.yaml', SourceType::Openapi),
    ], [], new Components([], [], [], []), []);
    $ec = new ErrorCollector();
    (new SourceUrlSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(2);
});
