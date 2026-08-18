<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Validator\Error;
use Alama\Arazzo\Validator\ValidationResult;
use Alama\Arazzo\Validator\Warning;

it('isValid true when no errors', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $r = new ValidationResult($doc, [], []);
    expect($r->isValid())->toBeTrue()
        ->and($r->toArray())->toBe(['valid' => true, 'errors' => [], 'warnings' => []]);
});

it('isValid false and toArray serializes errors/warnings', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $r = new ValidationResult($doc, [new Error('c', 'm', '/p')], [new Warning('w', 'wm', '/w')]);
    $arr = $r->toArray();
    expect($r->isValid())->toBeFalse()
        ->and($arr['valid'])->toBeFalse()
        ->and($arr['errors'])->toHaveCount(1)
        ->and($arr['warnings'])->toHaveCount(1);
});
