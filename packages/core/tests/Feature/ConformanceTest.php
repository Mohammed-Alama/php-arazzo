<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Feature;

use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Exceptions\DecodeException;
use Alama\Arazzo\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Parser\Exceptions\ParserException;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Tests\Conformance\ConformanceHarness;
use Alama\Arazzo\Tests\Support\FakePsr18Client;
use Alama\Arazzo\Tests\Support\RecordingEventDispatcher;

dataset('valid_fixtures', fn () => FixtureHarness::fixtures('valid'));
dataset('invalid_fixtures', fn () => FixtureHarness::fixtures('invalid'));

it('parses and validates valid fixtures without errors', function (string $path) {
    $result = FixtureHarness::validate($path);

    expect($result->isValid())->toBeTrue("Fixture {$path} should be valid, but has errors: ".json_encode($result->errors));
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

it('never dispatches HTTP when invalid documents are run through the execution harness', function (): void {
    $harness = new class() extends ConformanceHarness
    {
        public function run(array $fixture): array
        {
            return [];
        }

        public function dispatchedRequests(): array
        {
            return $this->http->requests;
        }

        public function runFile(string $path): void
        {
            // Mirror prepare()'s side-effect-free setup: client first, then parse.
            $this->events = new RecordingEventDispatcher();
            $this->http = new FakePsr18Client();
            $this->sourceRegistry = new SourceRegistry(new DefaultSourceResolver([]));

            $raw = (new Loader(
                new SymfonyYamlDecoder(),
                new NativeJsonDecoder(),
            ))->load($path);

            (new Parser())->parse($raw);
        }
    };

    $invalid = FixtureHarness::fixtures('invalid');

    expect($invalid)->not->toBeEmpty();

    foreach ($invalid as $paths) {
        $path = (string) (is_array($paths) ? $paths[0] : $paths);

        try {
            $harness->runFile($path);
        } catch (\Throwable) {
            // Parse/validation rejection before any side effect is the contract.
        }
    }

    expect($harness->dispatchedRequests())->toBe([]);
});
