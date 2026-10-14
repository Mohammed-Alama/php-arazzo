<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\Schema;

it('loads the repo schema and exposes the skeleton', function () {
    $schema = Schema::fromFile(dirname(__DIR__, 3).'/config/issues/schema.yaml');

    expect($schema->skeleton())->toBe([
        'core-metadata', 'problem-statement', 'acceptance-criteria', 'source-grounding', 'impact-profile',
    ]);
    expect($schema->requiredFor('bug'))->toContain('artifacts', 'runtime-state', 'epistemic-matrix');
    expect($schema->requiredFor('chore'))->toBe(['affected-surface']);
});

it('keeps the form copy in the schema, not the generator', function () {
    $schema = Schema::fromFile(dirname(__DIR__, 3).'/config/issues/schema.yaml');

    $section = $schema->section('core-metadata');

    expect($section)->toHaveKeys(['heading', 'label', 'placeholder'])
        ->and($section['heading'])->toContain('Core Metadata')
        ->and($section['label'])->not->toContain('#');
});

it('rejects a kind that both requires and exempts the same section', function () {
    Schema::fromArray([
        'version' => 1,
        'kinds' => ['bug'],
        'levels' => [],
        'skeleton' => [],
        'kind_sections' => ['bug' => ['requires' => ['x'], 'exempt' => ['x']]],
        'sections' => ['x' => ['heading' => 'X', 'label' => 'X', 'placeholder' => 'x']],
    ]);
})->throws(InvalidArgumentException::class);

it('rejects a kind referencing an undeclared section', function () {
    Schema::fromArray([
        'version' => 1,
        'kinds' => ['bug'],
        'levels' => [],
        'skeleton' => [],
        'kind_sections' => ['bug' => ['requires' => ['ghost']]],
        'sections' => [],
    ]);
})->throws(InvalidArgumentException::class);
