<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;
use Alama\Arazzo\Expression\SymbolTable;

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
