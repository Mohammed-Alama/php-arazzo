<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

/**
 * Validates an issue body, its labels and its level links against the schema.
 *
 * This is the *one* engine (spec D12): the gh shim, the CI gate and the
 * file-issue skill all reach their verdict through this class. Validation is
 * pure — no network, no filesystem — so every caller feeds it the same shape.
 */
final class Validator
{
    public function __construct(private readonly Schema $schema) {}

    /**
     * @param  array{
     *     body?: string,
     *     labels?: list<string>,
     *     parent?: int|null,
     *     parentLevel?: string|null,
     * }  $input
     * @return list<Finding>
     */
    public function validate(array $input): array
    {
        $body = $input['body'] ?? '';
        $labels = self::strings($input['labels'] ?? []);
        $parent = $input['parent'] ?? null;
        $parentLevel = $input['parentLevel'] ?? null;

        $parsed = SectionParser::parse($body);
        $findings = [];

        $kind = $this->resolveKind($labels, $findings);
        $level = $this->resolveLevel($labels, $parsed, $findings);

        if ($kind !== null) {
            $this->checkSections($kind, $parsed, $findings);
        }

        if ($level !== null) {
            $this->checkLevel($level, $parent, $parentLevel, $findings);
        }

        return $findings;
    }

    /** @param list<Finding> $findings */
    public static function isConforming(array $findings): bool
    {
        return $findings === [];
    }

    /**
     * The issue's kind is carried only by its labels (spec D8); exactly one
     * of the schema's kinds must be present.
     *
     * @param  list<string>  $labels
     * @param  list<Finding>  $findings
     */
    private function resolveKind(array $labels, array &$findings): ?string
    {
        $kinds = array_values(array_intersect($labels, $this->schema->kinds()));

        if ($kinds === []) {
            $findings[] = new Finding('missing-kind-label', sprintf(
                'Add exactly one kind label: %s.', implode(', ', $this->schema->kinds()),
            ));

            return null;
        }

        if (count($kinds) > 1) {
            $findings[] = new Finding('multiple-kind-labels', sprintf(
                'Issue carries multiple kind labels (%s); keep exactly one.', implode(', ', $kinds),
            ));

            return null;
        }

        return $kinds[0];
    }

    /**
     * The level comes from a `level:*` label when present, else from the
     * form's Level dropdown (spec ruling 7) so a human-filed issue is not
     * false-flagged.
     *
     * @param  list<string>  $labels
     * @param  array<string, list<string>>  $parsed
     * @param  list<Finding>  $findings
     */
    private function resolveLevel(array $labels, array $parsed, array &$findings): ?string
    {
        $levels = array_keys($this->schema->levels());
        $declared = array_values(array_intersect($labels, $levels));

        if (count($declared) > 1) {
            $findings[] = new Finding('multiple-level-labels', sprintf(
                'Issue carries multiple level labels (%s); keep exactly one.', implode(', ', $declared),
            ));

            return null;
        }

        if ($declared !== []) {
            return $declared[0];
        }

        foreach ($parsed[SectionParser::normalise('Level')] ?? [] as $block) {
            $candidate = trim($block);

            if (in_array($candidate, $levels, true)) {
                return $candidate;
            }
        }

        $findings[] = new Finding('missing-level-label', sprintf(
            'No level declared: add a level:* label or fill the Level field (%s).', implode(', ', $levels),
        ));

        return null;
    }

    /**
     * @param  array<string, list<string>>  $parsed
     * @param  list<Finding>  $findings
     */
    private function checkSections(string $kind, array $parsed, array &$findings): void
    {
        foreach ($this->schema->requiredIdsFor($kind) as $id) {
            $section = $this->schema->section($id);
            $key = SectionParser::normalise($section['heading']);

            if (!array_key_exists($key, $parsed)) {
                $findings[] = new Finding('missing-section', sprintf(
                    'Missing required section "%s".', $section['heading'],
                ));

                continue;
            }

            if (!$this->hasContent($parsed[$key], $section['placeholder'])) {
                $findings[] = new Finding('empty-section', sprintf(
                    'Section "%s" is empty — replace the placeholder with real content.', $section['heading'],
                ));
            }
        }
    }

    /**
     * A section is satisfied if any occurrence holds real content. A block is
     * empty when it is whitespace, an HTML comment, the schema placeholder, or
     * nothing but bare unchecked checkboxes.
     *
     * @param  list<string>  $blocks
     */
    private function hasContent(array $blocks, string $placeholder): bool
    {
        $expected = SectionParser::normalise($placeholder);

        foreach ($blocks as $block) {
            $text = trim(preg_replace('/<!--.*?-->/s', '', $block) ?? '');

            if ($text === '' || SectionParser::normalise($text) === $expected) {
                continue;
            }

            foreach (preg_split('/\R/', $text) ?: [] as $line) {
                $line = trim($line);

                // A written criterion counts; a bare unchecked box does not.
                if ($line !== '' && preg_match('/^[-*+]\s*\[\s*\]\s*$/u', $line) !== 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<Finding> $findings */
    private function checkLevel(string $level, ?int $parent, ?string $parentLevel, array &$findings): void
    {
        $parentRule = $this->schema->levels()[$level]['parent'] ?? null;
        $allowed = is_array($parentRule) ? self::strings($parentRule['allowed_parent_levels'] ?? []) : [];
        $required = is_array($parentRule) && ($parentRule['required'] ?? false);

        if ($parent === null) {
            if ($required) {
                $findings[] = new Finding('parent-missing', sprintf(
                    'A %s issue must sit under a parent (%s). Link one, or file the parent first.',
                    $level, implode(', ', $allowed),
                ));
            }

            return;
        }

        if ($parentRule === 'forbidden') {
            $findings[] = new Finding('parent-forbidden', sprintf(
                'A %s issue must not have a parent; remove parent #%d.', $level, $parent,
            ));

            return;
        }

        if ($parentLevel === null) {
            $findings[] = new Finding('parent-missing', sprintf(
                'parent #%d has no level:* label — add one or pick a new-style parent.', $parent,
            ));

            return;
        }

        $parentDefinition = $this->schema->levels()[$parentLevel] ?? null;

        if (is_array($parentDefinition) && ($parentDefinition['children'] ?? null) === 'forbidden') {
            $findings[] = new Finding('children-forbidden', sprintf(
                'parent #%d is a %s and cannot have children.', $parent, $parentLevel,
            ));

            return;
        }

        if (!in_array($parentLevel, $allowed, true)) {
            $findings[] = new Finding('parent-level-invalid', sprintf(
                'A %s issue must sit under %s; parent #%d is %s.',
                $level, implode(', ', $allowed), $parent, $parentLevel,
            ));
        }
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
