<?php

declare(strict_types=1);

use Alama\Arazzo\Tests\Conformance\FixtureRunner;
use Alama\Arazzo\Tests\Conformance\QueueFixtureRunner;

$fixtureFiles = glob(__DIR__ . '/fixtures/*.json') ?: [];

foreach ($fixtureFiles as $fixtureFile) {
    $fixture = json_decode((string) file_get_contents($fixtureFile), true, 512, JSON_THROW_ON_ERROR);
    $name = $fixture['name'] ?? basename($fixtureFile);

    test("conformance fixture: {$name}", function () use ($fixture) {
        $observed = (new FixtureRunner())->run($fixture);
        $expectation = $fixture['expect'];

        expect($observed['status'])->toBe($expectation['status']);
        expect($observed['steps'])->toBe($expectation['steps']);
        expect($observed['requests'])->toBe($expectation['requests']);
        expect($observed['outputs'])->toBe($expectation['outputs']);

        if (array_key_exists('errors', $expectation)) {
            expect($observed['errors'])->toBe($expectation['errors']);
        }

        if (array_key_exists('retries', $expectation)) {
            expect($observed['retries'])->toBe($expectation['retries']);
        }

        foreach ($expectation['requestHeaders'] ?? [] as $header => $value) {
            expect($observed['requestHeaders'][$header] ?? null)->toBe($value);
        }

        foreach ($expectation['eventsContain'] ?? [] as $eventClass) {
            expect($observed['events'])->toContain($eventClass);
        }
    });

    test("queue adapter parity: {$name}", function () use ($fixture, $name) {
        $sync = (new FixtureRunner())->run($fixture);
        $queued = (new QueueFixtureRunner())->run($fixture);

        // The queued adapter must produce the same observable outcome as
        // the synchronous one for every golden fixture.
        foreach (['status', 'steps', 'requests', 'outputs'] as $key) {
            expect($queued[$key])->toBe(
                $sync[$key],
                "queue parity mismatch on '{$key}' for fixture '{$name}'",
            );
        }
    });
}
