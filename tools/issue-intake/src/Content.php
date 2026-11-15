<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

/**
 * The one place raw issue text becomes structure.
 *
 * Every consumer that needs to read section bodies, requirement ids, scenario
 * headers, fenced blocks, bare permalinks or checklist items does it through
 * these static helpers, so the regular expressions live in exactly one place
 * (spec §D9). Section keys come from `SectionParser`, so schema headings and
 * form labels normalise to the same key.
 */
final class Content
{
    private function __construct() {}

    /**
     * All occurrences of one section joined with `"\n\n"`, or `''` when the
     * section id is absent from the parsed body.
     */
    public static function sectionText(array $parsed, Schema $schema, string $id): string
    {
        $key = SectionParser::normalise($schema->section($id)['heading']);

        if (!array_key_exists($key, $parsed)) {
            return '';
        }

        return implode("\n\n", $parsed[$key]);
    }

    /**
     * @return list<string>
     *
     * Splits a body into `S<n>` scenario blocks at every line-anchored
     * `S<n>` marker, after CRLF normalisation. Empty pieces are dropped.
     */
    public static function headingBlocks(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);

        $blocks = preg_split('/(?=^\s*S\d+\b)/m', $text) ?? [];

        return array_values(array_filter(
            $blocks,
            static fn (string $block): bool => $block !== '',
        ));
    }

    /**
     * @return array<string, string>
     *
     * `R<n>` => sentence, for every line-anchored `R<n>. ` occurrence.
     */
    public static function requirementIds(string $text): array
    {
        $ids = [];

        preg_match_all('/^\s*R(\d+)\.\s+(.+)$/m', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $ids['R'.$match[1]] = $match[2];
        }

        return $ids;
    }

    /**
     * The header of one `S<n>` scenario block, or `null` when it has none.
     *
     * @return ?array{id: string, refs: list<string>, quality: ?string}
     */
    public static function scenarioHeader(string $block): ?array
    {
        if (preg_match('/^S(\d+)\s*\((?<rest>[^)]*)\):/', $block, $matches) !== 1) {
            return null;
        }

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $matches['rest']),
        );

        $refs = array_values(array_filter(
            $parts,
            static fn (string $part): bool => preg_match('/^R\d+$/', $part) === 1,
        ));

        $nonRefs = array_values(array_filter(
            $parts,
            static fn (string $part): bool => preg_match('/^R\d+$/', $part) !== 1,
        ));

        $quality = $nonRefs !== [] ? $nonRefs[count($nonRefs) - 1] : null;

        return ['id' => 'S'.$matches[1], 'refs' => $refs, 'quality' => $quality];
    }

    /**
     * @return list<string>
     *
     * Splits on `/\R/` and trims a trailing `\r` from every line.
     */
    public static function lines(string $text): array
    {
        return array_map(
            static fn (string $line): string => rtrim($line, "\r"),
            preg_split('/\R/', $text) ?? [],
        );
    }

    /**
     * @return list<string>
     *
     * The text inside each triple-backtick fence, fence lines excluded.
     *
     * A fence counts only at column 0-3: CommonMark allows 0-3 leading
     * spaces, so a 4-space-indented fence renders as literal backticks and
     * is not a fence here.
     */
    public static function fencedBlocks(string $text): array
    {
        $blocks = [];
        $buffer = [];
        $inFence = false;

        foreach (self::lines($text) as $line) {
            if (!$inFence) {
                if (preg_match('/^(?: {0,3})```/', $line) === 1) {
                    $inFence = true;
                }

                continue;
            }

            if (preg_match('/^(?: {0,3})```/', $line) === 1) {
                $blocks[] = implode("\n", $buffer);
                $buffer = [];
                $inFence = false;

                continue;
            }

            $buffer[] = $line;
        }

        return $blocks;
    }

    /**
     * @return list<string>
     *
     * Bare GitHub blob permalinks, one per trimmed line.
     */
    public static function barePermalinks(string $text): array
    {
        $links = [];

        foreach (self::lines($text) as $line) {
            $line = trim($line);

            if (preg_match('~^https://github\.com/\S+/blob/[0-9a-f]{40}/\S+$~', $line) === 1) {
                $links[] = $line;
            }
        }

        return $links;
    }

    /**
     * @return list<string>
     *
     * GitHub task-list checklist items, one per matching trimmed line.
     */
    public static function checklistItems(string $text): array
    {
        $items = [];

        foreach (self::lines($text) as $line) {
            $line = trim($line);

            if (preg_match('/^[-*+]\s*\[(?: |x)\]/', $line) === 1) {
                $items[] = $line;
            }
        }

        return $items;
    }
}
