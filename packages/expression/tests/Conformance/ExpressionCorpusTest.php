<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Tests\Conformance;

use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser;

/** @internal stays out of the advertised contract; not part of the public API surface */
$fixtureDir = __DIR__.'/../fixtures/conformance/expressions';

/** @var list<array{0: array<string, mixed>}> $validDataset */
$validDataset = [];
foreach (['inputs', 'steps', 'request-response', 'workflows', 'sources-components', 'embedded'] as $topic) {
    $path = "$fixtureDir/$topic.json";
    if (!file_exists($path)) {
        continue;
    }
    $entries = json_decode(file_get_contents($path), true) ?? [];
    foreach ($entries as $entry) {
        if ($entry['valid'] === true) {
            $validDataset[] = [$entry];
        }
    }
}

/** @var list<array{0: string}> $invalidDataset */
$invalidDataset = [];
$path = "$fixtureDir/invalid.json";
if (file_exists($path)) {
    $entries = json_decode(file_get_contents($path), true) ?? [];
    foreach ($entries as $entry) {
        if ($entry['valid'] === false) {
            $invalidDataset[] = [$entry['expression']];
        }
    }
}

it('valid corpus entries parse and project as expected', function (array $entry): void {
    $expression = $entry['expression'];
    $parser = new Parser();
    $ast = $parser->parse($expression);
    $ref = $ast->mapToExpressionReference();

    $actual = [
        'kind' => $ref->kind->name,
        'target' => $ref->target,
        'part' => $ref->part,
        'name' => $ref->name,
        'httpPart' => $ref->httpPart,
        'jsonPointer' => $ref->jsonPointer,
    ];

    expect($actual)->toEqual($entry['projection'] ?? []);
})->with($validDataset);

it('invalid corpus entries throw ExpressionSyntaxException', function (string $expression): void {
    (new Parser())->parse($expression);
})->throws(ExpressionSyntaxException::class)
    ->with($invalidDataset);
