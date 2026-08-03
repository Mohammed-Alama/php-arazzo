<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;
use Alama\Arazzo\Validation\RuleSet;
use Alama\Arazzo\Validation\Validator;

class RecordingRule implements Rule
{
    public function code(): string
    {
        return 'r.a';
    }

    public function check(ArazzoDocument $d, SymbolTable $s, ErrorCollector $e): void
    {
        $e->error('r.a', 'msg', '/foo');
        $e->warning('r.a.warn', 'wmsg', '/bar');
    }
}

it('collects errors and warnings', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $result = (new Validator(new RuleSet([new RecordingRule()])))->validate($doc);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->code)->toBe('r.a')
        ->and($result->warnings)->toHaveCount(1);
});
