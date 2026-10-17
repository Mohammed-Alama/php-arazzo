<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\SourceLocation;

it('parses a path with an inclusive range', function () {
    $location = SourceLocation::parse('packages/evaluation/src/JsonPath.php:88-92');

    expect($location->path)->toBe('packages/evaluation/src/JsonPath.php')
        ->and($location->start)->toBe(88)
        ->and($location->end)->toBe(92)
        ->and($location->isRange())->toBeTrue()
        ->and($location->label())->toBe('packages/evaluation/src/JsonPath.php:88-92')
        ->and($location->anchor())->toBe('#L88-L92');
});

it('treats a bare line as a single-line point', function () {
    $location = SourceLocation::parse('src/Foo.php:7');

    expect($location->start)->toBe(7)
        ->and($location->end)->toBe(7)
        ->and($location->isRange())->toBeFalse()
        ->and($location->label())->toBe('src/Foo.php:7')
        ->and($location->anchor())->toBe('#L7');
});

it('strips a leading ./ or / from the path', function () {
    expect(SourceLocation::parse('./src/Foo.php:1')->path)->toBe('src/Foo.php')
        ->and(SourceLocation::parse('/src/Foo.php:1')->path)->toBe('src/Foo.php');
});

it('splits on the last colon so a colon in the path survives', function () {
    expect(SourceLocation::parse('weird:name/file.php:3')->path)->toBe('weird:name/file.php');
});

it('rejects malformed locations', function (string $spec) {
    SourceLocation::parse($spec);
})->with([
    'empty' => '',
    'no line' => 'src/Foo.php',
    'empty line' => 'src/Foo.php:',
    'zero line' => 'src/Foo.php:0',
    'reversed range' => 'src/Foo.php:9-2',
    'non-numeric line' => 'src/Foo.php:a',
    'no path' => ':1',
])->throws(InvalidArgumentException::class);
