<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Feature;

use Alama\Arazzo\Parser\Exceptions\DecodeException;
use Alama\Arazzo\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Parser\Exceptions\ParserException;

dataset('valid_fixtures', fn () => FixtureHarness::fixtures('valid'));
dataset('invalid_fixtures', fn () => FixtureHarness::fixtures('invalid'));

it('parses and validates valid fixtures without errors', function (string $path) {
    $result = FixtureHarness::validate($path);

    expect($result->isValid())->toBeTrue("Fixture {$path} should be valid, but has errors: " . json_encode($result->errors));
})->with('valid_fixtures');

it('detects errors in invalid fixtures', function (string $path) {
    try {
        $result = FixtureHarness::validate($path);
    } catch (\InvalidArgumentException $e) {
        self::fail("Fixture {$path} was rejected with a non-typed exception: {$e}");
    } catch (ParserException|LoaderException|DecodeException $e) {
        // A typed parse/load rejection is a valid outcome for an invalid fixture.
        expect($e->getMessage())->not->toBeEmpty();

        return;
    }

    expect($result->isValid())->toBeFalse("Fixture {$path} should be invalid, but validation passed!");
})->with('invalid_fixtures');
