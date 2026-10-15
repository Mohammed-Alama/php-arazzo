<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\FormGenerator;
use Alama\Tools\IssueIntake\SectionParser;
use Alama\Tools\IssueIntake\Validator;

/** @return array<int, array<string, mixed>> */
function formFields(array $form, string $type): array
{
    return array_values(array_filter(
        $form['body'],
        static fn (array $field): bool => $field['type'] === $type,
    ));
}

it('emits a leading level dropdown built from the schema levels', function () {
    $level = formFields((new FormGenerator(arazzoSchema()))->forKind('kind:bugfix'), 'dropdown')[0];

    expect($level['id'])->toBe('level')
        ->and($level['attributes']['label'])->toBe('Level')
        ->and($level['attributes']['options'])->toBe(['level:epic', 'level:story', 'level:task'])
        ->and($level['validations']['required'])->toBeTrue();
});

it('emits one textarea per section, in visual order, using the schema label', function () {
    $schema = arazzoSchema();

    $fields = formFields((new FormGenerator($schema))->forKind('kind:bugfix'), 'textarea');

    expect(array_column(array_column($fields, 'attributes'), 'label'))->toBe(
        array_map(static fn (string $id): string => $schema->section($id)['label'], $schema->requiredIdsFor('kind:bugfix')),
    )->and(array_map(static fn (array $field): bool => $field['validations']['required'], $fields))
        ->toBe(array_fill(0, count($fields), true));
});

it('marks a section the kind only offers as optional as not required', function () {
    $fields = formFields((new FormGenerator(arazzoSchema()))->forKind('kind:feature'), 'textarea');
    $byId = array_column($fields, null, 'id');

    expect($byId)->toHaveKey('source-grounding')
        ->and($byId['source-grounding']['validations']['required'])->toBeFalse()
        ->and($byId['feature-scope']['validations']['required'])->toBeTrue();
});

it('round-trips every form label back to its schema heading', function () {
    $schema = arazzoSchema();
    $generator = new FormGenerator($schema);

    foreach ($schema->kinds() as $kind) {
        $ids = [...$schema->requiredIdsFor($kind), ...$schema->optionalFor($kind)];
        $expected = array_map(
            static fn (string $id): string => SectionParser::normalise($schema->section($id)['heading']),
            $ids,
        );
        $actual = array_map(
            static fn (string $label): string => SectionParser::normalise($label),
            array_column(array_column(formFields($generator->forKind($kind), 'textarea'), 'attributes'), 'label'),
        );

        sort($expected);
        sort($actual);

        expect($actual)->toBe($expected, "kind {$kind} labels must round-trip");
    }
});

it('carries the kind label GitHub applies automatically', function () {
    $generator = new FormGenerator(arazzoSchema());

    expect($generator->forKind('kind:bugfix')['labels'])->toBe(['kind:bugfix'])
        ->and($generator->forKind('kind:feature')['labels'])->toBe(['kind:feature'])
        ->and($generator->forKind('kind:chore')['labels'])->toBe(['kind:chore']);
});

it('maps each kind to its committed form filename', function () {
    $generator = new FormGenerator(arazzoSchema());

    expect($generator->filename('kind:bugfix'))->toBe('kind-bugfix.yml')
        ->and($generator->filename('kind:feature'))->toBe('kind-feature.yml')
        ->and($generator->filename('kind:chore'))->toBe('kind-chore.yml');
});

it('produces forms whose filled body conforms to the validator', function () {
    $schema = arazzoSchema();
    $generator = new FormGenerator($schema);

    foreach ($schema->kinds() as $kind) {
        $form = $generator->forKind($kind);
        $body = '';

        foreach ($form['body'] as $field) {
            $body .= '### '.$field['attributes']['label']."\n\n";
            $body .= match (true) {
                $field['id'] === 'level' => "level:epic\n\n",
                $field['validations']['required'] === false => "_No response_\n\n",
                default => "filled {$field['id']}\n\n",
            };
        }

        // Epics require a parent (package-epic); all kinds get level:epic in this test.
        $parent = 1;
        $parentLevel = 'level:package';

        $findings = (new Validator($schema))->validate([
            'body' => $body,
            'labels' => $form['labels'],
            'parent' => $parent,
            'parentLevel' => $parentLevel,
        ]);

        expect($findings)->toBe([], "a filled {$kind} form must conform");
    }
});
