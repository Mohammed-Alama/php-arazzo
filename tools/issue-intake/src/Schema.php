<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * The single source of truth for issue structure (spec D9).
 *
 * Loads and validates config/issues/schema.yaml. Every inconsistency is a
 * load-time error: a schema that half-works would recreate the drift it
 * exists to prevent (anti-goal A3).
 */
final class Schema
{
    /** @var list<string> */
    private const SECTION_COPY = ['heading', 'label', 'description', 'placeholder'];

    /**
     * @param  list<string>  $kinds
     * @param  list<string>  $skeleton
     * @param  array<string, array<string, mixed>>  $levels
     * @param  array<string, array{requires: list<string>, optional: list<string>, exempt: list<string>}>  $kindSections
     * @param  array<string, array<string, string>>  $sections
     */
    private function __construct(
        private readonly array $kinds,
        private readonly array $skeleton,
        private readonly array $levels,
        private readonly array $kindSections,
        private readonly array $sections,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Schema file not found: {$path}");
        }

        $parsed = Yaml::parseFile($path);

        if (!is_array($parsed)) {
            throw new InvalidArgumentException("Schema file did not parse to a mapping: {$path}");
        }

        return self::fromArray($parsed);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $kinds = self::stringList($data['kinds'] ?? [], 'kinds');
        $skeleton = self::stringList($data['skeleton'] ?? [], 'skeleton');
        $levels = self::stringKeyedMap($data['levels'] ?? [], 'levels');
        $rawKindSections = self::stringKeyedMap($data['kind_sections'] ?? [], 'kind_sections');
        $sections = self::stringKeyedMap($data['sections'] ?? [], 'sections');

        foreach ($skeleton as $id) {
            self::assertDeclared($id, $sections, 'skeleton');
        }

        foreach ($kinds as $kind) {
            if (!array_key_exists($kind, $rawKindSections)) {
                throw new InvalidArgumentException("Kind \"{$kind}\" has no kind_sections entry.");
            }
        }

        $kindSections = [];

        foreach ($rawKindSections as $kind => $selectors) {
            if (!is_array($selectors)) {
                throw new InvalidArgumentException("kind_sections.\"{$kind}\" must be a mapping.");
            }

            $requires = self::stringList($selectors['requires'] ?? [], "kind_sections.{$kind}.requires");
            $optional = self::stringList($selectors['optional'] ?? [], "kind_sections.{$kind}.optional");
            $exempt = self::stringList($selectors['exempt'] ?? [], "kind_sections.{$kind}.exempt");

            foreach ([...$requires, ...$optional, ...$exempt] as $id) {
                self::assertDeclared($id, $sections, "kind_sections.{$kind}");
            }

            foreach (array_intersect($requires, [...$optional, ...$exempt]) as $id) {
                throw new InvalidArgumentException(
                    "Kind \"{$kind}\" both requires and overrides section \"{$id}\".",
                );
            }

            foreach (array_intersect($optional, $exempt) as $id) {
                throw new InvalidArgumentException(
                    "Kind \"{$kind}\" lists section \"{$id}\" as both optional and exempt.",
                );
            }

            $kindSections[$kind] = ['requires' => $requires, 'optional' => $optional, 'exempt' => $exempt];
        }

        foreach ($levels as $label => $spec) {
            if (!str_starts_with($label, 'level:')) {
                throw new InvalidArgumentException("Level label \"{$label}\" must use the \"level:\" prefix.");
            }

            if (!is_array($spec)) {
                throw new InvalidArgumentException("levels.\"{$label}\" must be a mapping.");
            }

            $parent = $spec['parent'] ?? null;

            if (is_array($parent)) {
                $allowed = self::stringList($parent['allowed_parent_levels'] ?? [], "levels.{$label}.allowed_parent_levels");

                foreach ($allowed as $level) {
                    if (!array_key_exists($level, $levels)) {
                        throw new InvalidArgumentException(
                            "Level \"{$label}\" allows an unknown parent level \"{$level}\".",
                        );
                    }
                }
            }
        }

        foreach ($sections as $id => $section) {
            if (!is_array($section)) {
                throw new InvalidArgumentException("sections.\"{$id}\" must be a mapping.");
            }

            foreach (self::SECTION_COPY as $key) {
                if (!isset($section[$key]) || !is_string($section[$key]) || trim($section[$key]) === '') {
                    throw new InvalidArgumentException("Section \"{$id}\" is missing the \"{$key}\" copy.");
                }
            }

            $sections[$id] = array_intersect_key($section, array_flip(self::SECTION_COPY));
        }

        return new self($kinds, $skeleton, $levels, $kindSections, $sections);
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return $this->kinds;
    }

    /** @return array<string, array<string, mixed>> */
    public function levels(): array
    {
        return $this->levels;
    }

    /** @return list<string> */
    public function skeleton(): array
    {
        return $this->skeleton;
    }

    /** @return list<string> */
    public function requiredFor(string $kind): array
    {
        return $this->selectors($kind)['requires'];
    }

    /** @return list<string> */
    public function optionalFor(string $kind): array
    {
        return $this->selectors($kind)['optional'];
    }

    /** @return list<string> */
    public function exemptFor(string $kind): array
    {
        return $this->selectors($kind)['exempt'];
    }

    /**
     * The section ids a body of this kind must satisfy — skeleton plus the
     * kind's extras, minus anything the kind overrides — in declaration
     * (visual) order.
     *
     * @return list<string>
     */
    public function requiredIdsFor(string $kind): array
    {
        $wanted = [...$this->skeleton, ...$this->requiredFor($kind)];
        $overridden = [...$this->optionalFor($kind), ...$this->exemptFor($kind)];

        return array_values(array_filter(
            $this->sectionIds(),
            static fn (string $id): bool => in_array($id, $wanted, true)
                && !in_array($id, $overridden, true),
        ));
    }

    /** @return array<string, string> */
    public function section(string $id): array
    {
        if (!array_key_exists($id, $this->sections)) {
            throw new InvalidArgumentException("Unknown section \"{$id}\".");
        }

        return $this->sections[$id];
    }

    /** @return list<string> */
    public function sectionIds(): array
    {
        return array_keys($this->sections);
    }

    public function hasSection(string $id): bool
    {
        return array_key_exists($id, $this->sections);
    }

    /** @return array{requires: list<string>, optional: list<string>, exempt: list<string>} */
    private function selectors(string $kind): array
    {
        if (!array_key_exists($kind, $this->kindSections)) {
            throw new InvalidArgumentException("Unknown kind \"{$kind}\".");
        }

        return $this->kindSections[$kind];
    }

    /** @return list<string> */
    private static function stringList(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("{$context} must be a list.");
        }

        $list = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException("{$context} must contain only strings.");
            }

            $list[] = $item;
        }

        return $list;
    }

    /** @return array<string, mixed> */
    private static function stringKeyedMap(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("{$context} must be a mapping.");
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException("{$context} keys must be strings.");
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $sections */
    private static function assertDeclared(string $id, array $sections, string $context): void
    {
        if (!array_key_exists($id, $sections)) {
            throw new InvalidArgumentException("{$context} references undeclared section \"{$id}\".");
        }
    }
}
