<?php

declare(strict_types=1);

namespace Alama\Tools\IssueIntake;

require_once __DIR__.'/PermalinkResolver.php';

/**
 * One inventory row per issue: shape, kind/level, dialect, content counts,
 * findings and readiness.
 *
 * Produced from `gh issue list --json` output or a directory of draft
 * markdown files. Consumes `Validator::report()` and `Validator::readiness()`.
 *
 * @template row
 *
 * @psalm-return list<row>
 */
final class IssueInventory
{
    public const FORMAT_TSV = 'tsv';

    public const FORMAT_JSON = 'json';

    /** @return list<array{number:int, title:string, kind:?string, level:?string, dialect:string, missing:list<string>, requirements:int, scenarios:int, tests:int, permalinks:int, permalink_shas_resolve:int, errors:list<string>, warnings:list<string>, ready:bool}> */
    public static function fromGhJson(array $issues, Validator $validator): array
    {
        $schema = self::schema();
        $resolver = new GitPermalinkResolver(dirname(__DIR__, 3));

        $rows = [];

        foreach ($issues as $issue) {
            $rows[] = self::row(
                (int) ($issue['number'] ?? 0),
                (string) ($issue['title'] ?? ''),
                (string) ($issue['body'] ?? ''),
                self::labelNames($issue['labels'] ?? []),
                $validator,
                $schema,
                $resolver,
            );
        }

        return $rows;
    }

    /** @return list<array{number:int, title:string, kind:?string, level:?string, dialect:string, missing:list<string>, requirements:int, scenarios:int, tests:int, permalinks:int, permalink_shas_resolve:int, errors:list<string>, warnings:list<string>, ready:bool}> */
    public static function fromDraftDir(string $dir, Validator $validator): array
    {
        $schema = self::schema();
        $resolver = new GitPermalinkResolver(dirname(__DIR__, 3));

        $rows = [];

        foreach (glob($dir.'/*.md') ?? [] as $path) {
            $body = (string) @file_get_contents($path);
            $title = self::draftTitle($body, $path);

            $rows[] = self::row(
                0,
                $title,
                $body,
                [],
                $validator,
                $schema,
                $resolver,
            );
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['title'], $b['title']));

        return $rows;
    }

    /**
     * @param  array{number: int, title: string, body: string, labels: list<string>}  $input
     * @return array{number:int, title:string, kind:?string, level:?string, dialect:string, missing:list<string>, requirements:int, scenarios:int, tests:int, permalinks:int, permalink_shas_resolve:int, errors:list<string>, warnings:list<string>, ready:bool}
     */
    private static function row(int $number, string $title, string $body, array $labels, Validator $validator, Schema $schema, GitPermalinkResolver $resolver): array
    {
        $input = ['body' => $body, 'labels' => $labels, 'title' => $title];

        $classified = $validator->classify($input);
        $kind = $classified['kind'];
        $level = $classified['level'];

        $dialect = self::classifyDialect($body, $schema);
        $missing = $kind === null ? [] : self::missingSections($body, $kind, $schema);

        $parsed = SectionParser::parse($body);
        $requirements = count(Content::requirementIds(Content::sectionText($parsed, $schema, 'problem-statement')));

        $acText = Content::sectionText($parsed, $schema, 'acceptance-criteria');
        $scenarios = 0;
        $tests = 0;

        foreach (Content::headingBlocks($acText) as $block) {
            if (Content::scenarioHeader($block) !== null) {
                $scenarios++;
                if (preg_match('/^\s*Test:\s*\S+/m', $block) === 1) {
                    $tests++;
                }
            }
        }

        $permalinks = Content::barePermalinks($body);
        $permalinkShasResolve = 0;

        foreach ($permalinks as $url) {
            if (preg_match('/blob\/([0-9a-f]{40})/', $url, $match) !== 1) {
                continue;
            }

            $sha = $match[1];
            $path = self::permalinkPath($url);

            if ($resolver->isKnown($sha) && $resolver->pathExistsAt($sha, $path)) {
                $permalinkShasResolve++;
            }
        }

        $findings = $validator->report($input);
        $errors = [];
        $warnings = [];

        foreach ($findings as $finding) {
            if ($finding->severity === 'error') {
                $errors[] = $finding->code;
            } else {
                $warnings[] = $finding->code;
            }
        }

        $ready = $validator->readiness($input)['ready'];

        return [
            'number' => $number,
            'title' => $title,
            'kind' => $kind,
            'level' => $level,
            'dialect' => $dialect,
            'missing' => $missing,
            'requirements' => $requirements,
            'scenarios' => $scenarios,
            'tests' => $tests,
            'permalinks' => count($permalinks),
            'permalink_shas_resolve' => $permalinkShasResolve,
            'errors' => $errors,
            'warnings' => $warnings,
            'ready' => $ready,
        ];
    }

    private static function classifyDialect(string $body, Schema $schema): string
    {
        $parsed = SectionParser::parse($body);
        $keys = array_keys($parsed);

        $v2Headings = array_map(
            static fn (string $id): string => SectionParser::normalise($schema->section($id)['heading']),
            $schema->sectionIds(),
        );

        if (self::isSubsequence($v2Headings, $keys)) {
            return 'v2';
        }

        $acKey = SectionParser::normalise($schema->section('acceptance-criteria')['heading']);

        if (str_contains($body, 'Behavioral Specification') && !array_key_exists($acKey, $parsed)) {
            return 'heavy';
        }

        return 'other';
    }

    /** @return list<string> */
    private static function missingSections(string $body, string $kind, Schema $schema): array
    {
        $parsed = SectionParser::parse($body);
        $missing = [];

        foreach ($schema->requiredIdsFor($kind) as $id) {
            $key = SectionParser::normalise($schema->section($id)['heading']);

            if (!array_key_exists($key, $parsed)
                || !self::hasContent($parsed[$key], $schema->section($id)['placeholder'])) {
                $missing[] = $id;
            }
        }

        return $missing;
    }

    /** @param  list<string>  $blocks  @param  string  $placeholder */
    private static function hasContent(array $blocks, string $placeholder): bool
    {
        $expected = SectionParser::normalise($placeholder);

        foreach ($blocks as $block) {
            $text = trim(preg_replace('/<!--.*?-->/s', '', $block) ?? '');

            if ($text === '' || $text === '_No response_' || SectionParser::normalise($text) === $expected) {
                continue;
            }

            foreach (preg_split('/\R/', $text) ?: [] as $line) {
                $line = trim($line);

                if ($line !== '' && preg_match('/^[-*+]\s*\[\s*\]\s*$/u', $line) !== 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  list<string>  $needles  @param  list<string>  $haystack */
    private static function isSubsequence(array $needles, array $haystack): bool
    {
        if ($needles === []) {
            return true;
        }

        $i = 0;

        foreach ($haystack as $key) {
            if ($key === $needles[$i]) {
                $i++;

                if ($i === count($needles)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function draftTitle(string $body, string $path): string
    {
        foreach (Content::lines($body) as $line) {
            if (preg_match('/^#\s*Draft issue\s+(\d+)/i', trim($line), $match) === 1) {
                return 'Draft issue '.$match[1];
            }
        }

        return self::slug(basename($path, '.md'));
    }

    private static function slug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name, '-.')));
        $slug = trim($slug, '-');

        return $slug === '' ? basename($name) : $slug;
    }

    private static function permalinkPath(string $url): string
    {
        if (preg_match('~^https://github\.com/[^/]+/[^/]+/blob/[0-9a-f]{40}/(.+)$~', $url, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    /** @param  list<array<string, string>|string>  $labels  @return list<string> */
    private static function labelNames(array $labels): array
    {
        $names = [];

        foreach ($labels as $label) {
            $names[] = is_array($label) ? ($label['name'] ?? '') : (string) $label;
        }

        return $names;
    }

    private static function schema(): Schema
    {
        return Schema::fromFile(dirname(__DIR__, 3).'/config/issues/schema.yaml');
    }
}
