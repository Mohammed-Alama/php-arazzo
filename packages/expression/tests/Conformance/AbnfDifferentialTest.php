<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Tests\Conformance;

use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser;
use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfGenerator;
use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfGrammar;
use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfMutator;
use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfRecognizer;

/** @internal stays out of the advertised contract; not part of the public API surface */
$grammarPath = __DIR__.'/../../resources/grammar/arazzo-runtime-expression-1.1.0.abnf';
$grammar = AbnfGrammar::parse(file_get_contents($grammarPath));
$divergences = json_decode(
    file_get_contents(__DIR__.'/../fixtures/conformance/divergences.json'),
    true,
) ?? [];

$divergenceByInput = [];
foreach ($divergences as $d) {
    $divergenceByInput[$d['input']] = $d;
}

$caseCount = (int) ($_ENV['ARAZZO_ABNF_CASES'] ?? 500);

$freshObjects = function () use ($grammar): array {
    $recognizer = new AbnfRecognizer($grammar);
    $generator = new AbnfGenerator($grammar, 42);
    $mutator = new AbnfMutator(7);
    $parser = new Parser();

    return compact('recognizer', 'generator', 'mutator', 'parser');
};

$parserAccepts = function (Parser $p, string $input): bool {
    try {
        $p->parse($input);

        return true;
    } catch (ExpressionSyntaxException $e) {
        return false;
    }
};

it('generated strings are accepted by the parser', function () use (
    $freshObjects,
    $divergenceByInput,
    $caseCount,
    $parserAccepts
): void {
    $objects = $freshObjects();
    $generator = $objects['generator'];
    $parser = $objects['parser'];

    $failures = [];

    for ($i = 0; $i < $caseCount; $i++) {
        $input = $generator->generate('expression');

        if (isset($divergenceByInput[$input])) {
            continue;
        }

        if (!$parserAccepts($parser, $input)) {
            $failures[] = $input;
        }
    }

    expect($failures)->toBe([]);
});

it('mutated strings: oracle and parser agree', function () use (
    $freshObjects,
    $divergenceByInput,
    $caseCount,
    $parserAccepts
): void {
    $objects = $freshObjects();
    $recognizer = $objects['recognizer'];
    $generator = $objects['generator'];
    $mutator = $objects['mutator'];
    $parser = $objects['parser'];

    $mismatches = [];

    for ($i = 0; $i < $caseCount; $i++) {
        $input = $generator->generate('expression');
        $mutated = $mutator->mutate($input);

        if ($mutated === null) {
            continue;
        }

        if (isset($divergenceByInput[$mutated])) {
            continue;
        }

        $oracleAccepts = $recognizer->matches('expression', $mutated);
        $parserAcceptsResult = $parserAccepts($parser, $mutated);

        if ($oracleAccepts !== $parserAcceptsResult) {
            $mismatches[] = $mutated;
        }
    }

    expect($mismatches)->toBe([]);
});

it('registered divergences still diverge (not stale)', function () use (
    $freshObjects,
    $divergenceByInput,
    $parserAccepts
): void {
    $objects = $freshObjects();
    $recognizer = $objects['recognizer'];
    $parser = $objects['parser'];

    $stale = [];

    foreach ($divergenceByInput as $input => $d) {
        $oracleAccepts = $recognizer->matches('expression', $input);
        $parserAcceptsResult = $parserAccepts($parser, $input);

        if ($parserAcceptsResult === $oracleAccepts) {
            $stale[] = $input;
        }
    }

    expect($stale)->toBe([]);
});

it('known divergence candidates still diverge', function () use (
    $freshObjects,
    $divergenceByInput,
    $parserAccepts
): void {
    $objects = $freshObjects();
    $recognizer = $objects['recognizer'];
    $parser = $objects['parser'];

    $failures = [];

    foreach ($divergenceByInput as $input => $d) {
        $expectedOracle = $d['oracle'] === 'accept';
        $expectedParser = $d['parser'] === 'accept';

        $actualOracle = $recognizer->matches('expression', $input);
        $actualParser = $parserAccepts($parser, $input);

        if ($actualOracle !== $expectedOracle || $actualParser !== $expectedParser) {
            $failures[] = $input;
        }
    }

    expect($failures)->toBe([]);
});
