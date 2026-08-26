<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Feature;

it('detects complex step dependsOn cycle in edge-case fixture', function () {
    $path = __DIR__.'/../fixtures/edge-cases/complex-cyclic-dependency.arazzo.yaml';

    $result = FixtureHarness::validate($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->errors)->not->toBeEmpty();

    $hasCycleError = false;
    foreach ($result->errors as $error) {
        if ($error->code === 'step.dependson_no_cycle') {
            $hasCycleError = true;
            expect($error->message)->toContain('stepB -> stepC -> stepD -> stepE -> stepB');
        }
    }

    expect($hasCycleError)->toBeTrue('Failed to find step.dependson_no_cycle error in the validation result.');
});
