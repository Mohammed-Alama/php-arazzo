<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

use InvalidArgumentException;

/**
 * Renders a GitHub issue form from the schema (spec A2).
 *
 * The form is generated, never hand-maintained, so it cannot drift from the
 * sections the validator requires (anti-goal A3). A body filled through the
 * form is conforming by construction: the kind label is applied by GitHub
 * itself, the Level dropdown carries the level, and every required section is
 * a required field.
 */
final class FormGenerator
{
    /**
     * Form chrome per kind. Not section copy — the headings, labels,
     * descriptions and placeholders all come from the schema — so it lives
     * here rather than widening the schema contract.
     *
     * @var array<string, array{name: string, description: string, title: string, file: string}>
     */
    private const FORMS = [
        'kind:feature' => [
            'name' => 'Feature request',
            'description' => 'Request a new capability or an improvement.',
            'title' => '[Feature] ',
            'file' => 'kind-feature.yml',
        ],
        'kind:bugfix' => [
            'name' => 'Bug fix',
            'description' => 'Report something that is broken or behaves incorrectly.',
            'title' => '[Bugfix] ',
            'file' => 'kind-bugfix.yml',
        ],
        'kind:release' => [
            'name' => 'Release task',
            'description' => 'Cut a release, publish artifacts, or update changelogs.',
            'title' => '[Release] ',
            'file' => 'kind-release.yml',
        ],
        'kind:chore' => [
            'name' => 'Chore',
            'description' => 'Maintenance, tooling or cleanup — no user-facing change.',
            'title' => '[Chore] ',
            'file' => 'kind-chore.yml',
        ],
    ];

    public function __construct(private readonly Schema $schema) {}

    /**
     * A complete GitHub issue-form document for one kind.
     *
     * @return array{name: string, description: string, title: string, labels: list<string>, body: list<array<string, mixed>>}
     */
    public function forKind(string $kind): array
    {
        $meta = $this->meta($kind);
        $required = $this->schema->requiredIdsFor($kind);
        $optional = $this->schema->optionalFor($kind);

        $body = [$this->levelDropdown()];

        foreach ($this->schema->sectionIds() as $id) {
            if (!in_array($id, $required, true) && !in_array($id, $optional, true)) {
                continue;
            }

            $body[] = $this->sectionField($id, in_array($id, $required, true));
        }

        return [
            'name' => $meta['name'],
            'description' => $meta['description'],
            'title' => $meta['title'],
            'labels' => [$kind],
            'body' => $body,
        ];
    }

    public function filename(string $kind): string
    {
        return $this->meta($kind)['file'];
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return $this->schema->kinds();
    }

    /** @return array<string, mixed> */
    private function levelDropdown(): array
    {
        return [
            'type' => 'dropdown',
            'id' => 'level',
            'attributes' => [
                'label' => 'Level',
                'description' => 'Where this sits in the hierarchy: Epic → Story → Task.',
                'options' => $this->schema->formLevels(),
            ],
            'validations' => ['required' => true],
        ];
    }

    /** @return array<string, mixed> */
    private function sectionField(string $id, bool $required): array
    {
        $section = $this->schema->section($id);

        return [
            'type' => 'textarea',
            'id' => $id,
            'attributes' => [
                'label' => $section['label'],
                'description' => $section['description'],
                'placeholder' => $section['placeholder'],
            ],
            'validations' => ['required' => $required],
        ];
    }

    /** @return array{name: string, description: string, title: string, file: string} */
    private function meta(string $kind): array
    {
        return self::FORMS[$kind]
            ?? throw new InvalidArgumentException("No form metadata for kind \"{$kind}\".");
    }
}
