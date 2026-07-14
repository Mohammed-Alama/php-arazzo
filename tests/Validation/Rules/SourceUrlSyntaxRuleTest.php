<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\SourceUrlSyntaxRule;

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
