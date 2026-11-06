<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Tests\Conformance;

use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
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
        // Embedded-template entries carry `template`/`segments` instead of a
        // bare `expression`; they are exercised by the splitter test below.
        if (($entry['valid'] ?? true) === true && isset($entry['expression'])) {
            $validDataset[] = [$entry];
        }
    }
}

/** @var list<array{0: array<string, mixed>}> $invalidDataset */
$invalidDataset = [];
$path = "$fixtureDir/invalid.json";
if (file_exists($path)) {
    $entries = json_decode(file_get_contents($path), true) ?? [];
    foreach ($entries as $entry) {
        if ($entry['valid'] === false) {
            $invalidDataset[] = [$entry];
        }
    }
}

/** @var list<array{0: array<string, mixed>}> $embeddedDataset */
$embeddedDataset = [];
$path = "$fixtureDir/embedded.json";
if (file_exists($path)) {
    $entries = json_decode(file_get_contents($path), true) ?? [];
    foreach ($entries as $entry) {
        if (array_key_exists('template', $entry)) {
            $embeddedDataset[] = [$entry];
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

it('invalid corpus entries throw ExpressionSyntaxException with a real offset', function (array $entry): void {
    $thrown = false;

    try {
        (new Parser())->parse($entry['expression']);
    } catch (ExpressionSyntaxException $e) {
        $thrown = true;

        expect($e->offset)->toBeGreaterThanOrEqual(0)
            ->and($e->expression)->toBe($entry['expression']);

        if (array_key_exists('errorOffset', $entry)) {
            expect($e->offset)->toBe($entry['errorOffset']);
        }
    }

    expect($thrown)->toBeTrue();
})->with($invalidDataset);

it('splits embedded templates into the expected segments', function (array $entry): void {
    $version = SpecVersion::fromRaw($entry['specVersion']);

    if (($entry['valid'] ?? true) === false) {
        $thrown = false;

        try {
            (new ExpressionEngine())->parseEmbedded($entry['template'], $version);
        } catch (ExpressionSyntaxException $e) {
            $thrown = true;

            expect($e->expression)->toBe($entry['template'])
                ->and($e->offset)->toBe($entry['errorOffset']);
        }

        expect($thrown)->toBeTrue();

        return;
    }

    $actual = array_map(
        static fn ($segment): array => [
            'isExpression' => $segment->isExpression,
            'text' => $segment->text,
            'offset' => $segment->offset,
        ],
        (new ExpressionEngine())->parseEmbedded($entry['template'], $version),
    );

    expect($actual)->toBe($entry['segments']);
})->with($embeddedDataset);
