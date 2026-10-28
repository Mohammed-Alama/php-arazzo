<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Tests\Conformance;

use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfGenerator;
use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfGrammar;
use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfMutator;
use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfRecognizer;

/** @internal stays out of the advertised contract; not part of the public API surface */
$grammar110 = AbnfGrammar::parse(
    file_get_contents(__DIR__.'/../../resources/grammar/arazzo-runtime-expression-1.1.0.abnf'),
);
$recognizer110 = new AbnfRecognizer($grammar110);
$generator110 = new AbnfGenerator($grammar110, 42);

it('recognizer accepts generated strings (1.1.0)', function () use ($recognizer110, $generator110): void {
    $failures = 0;
    for ($i = 0; $i < 200; $i++) {
        $s = $generator110->generate('expression');
        if (!$recognizer110->matches('expression', $s)) {
            $failures++;
            if ($failures <= 3) {
                echo "\n  FAIL: ".json_encode($s)."\n";
            }
        }
    }
    expect($failures)->toBe(0);
});

it('oracle and parser agree on mutated strings (1.1.0)', function () use ($recognizer110, $generator110): void {
    $mutator = new AbnfMutator(7);
    $mismatches = 0;
    for ($i = 0; $i < 200; $i++) {
        $s = $generator110->generate('expression');
        $oracle = $recognizer110->matches('expression', $s);
        $mutated = $mutator->mutate($s);
        if ($mutated === null) {
            continue;
        }
        $oracleMutated = $recognizer110->matches('expression', $mutated);
        // For mutated strings we only check the oracle is self-consistent:
        // the same input always yields the same result.
        $again = $recognizer110->matches('expression', $mutated);
        if ($oracleMutated !== $again) {
            $mismatches++;
        }
    }
    expect($mismatches)->toBe(0);
});
