<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

/**
 * Splits an issue body into sections keyed by a normalised heading.
 *
 * Heading matching is normalised, not exact (spec §A1): GitHub issue forms
 * emit `### Core Metadata & Alignment` while the schema headings are
 * emoji-numbered (`🏗️ 1. Core Metadata & Alignment`). Both must land on the
 * same key or every human-filed issue would be flagged.
 */
final class SectionParser
{
    /**
     * @return array<string, list<string>> normalised heading => one content block per occurrence
     */
    public static function parse(string $body): array
    {
        $sections = [];
        $current = null;
        $buffer = [];
        $fence = null;

        $flush = static function () use (&$sections, &$current, &$buffer): void {
            if ($current === null) {
                return;
            }

            $sections[$current][] = trim(implode("\n", $buffer));
            $buffer = [];
        };

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if ($fence !== null) {
                if (self::closesFence($line, $fence)) {
                    $fence = null;
                }

                $buffer[] = $line;

                continue;
            }

            $marker = self::opensFence($line);

            if ($marker !== null) {
                $fence = $marker;
                $buffer[] = $line;

                continue;
            }

            if (preg_match('/^\s{0,3}#{1,6}\s+(?<heading>.*?)\s*#*\s*$/u', $line, $matches) === 1) {
                $flush();

                $current = self::normalise($matches['heading']);
                $sections[$current] ??= [];

                continue;
            }

            if ($current !== null) {
                $buffer[] = $line;
            }
        }

        $flush();

        return $sections;
    }

    /**
     * Normalise a heading so the schema heading and the form label compare equal.
     */
    public static function normalise(string $heading): string
    {
        // Anything that is not a letter, number or space: emoji (and their
        // variation selectors), `§`, numbering dots, `&`, punctuation.
        $heading = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $heading) ?? '';

        // A leading "Section" word and/or a decorative numeral run.
        $heading = preg_replace('/^\s*(?:section)?\s*[\d.]*\s*/iu', '', $heading) ?? '';

        $heading = strtolower(preg_replace('/\s+/u', ' ', trim($heading)) ?? '');

        return trim($heading, " \t\n\r\0\x0B-_.|#");
    }

    private static function opensFence(string $line): ?string
    {
        if (preg_match('/^\s{0,3}(?<marker>`{3,}|~{3,})/', $line, $matches) === 1) {
            return $matches['marker'][0];
        }

        return null;
    }

    private static function closesFence(string $line, string $marker): bool
    {
        return str_starts_with(ltrim($line), str_repeat($marker, 3));
    }
}
