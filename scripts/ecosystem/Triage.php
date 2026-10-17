<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Pure triage logic for the rewritten `ecosystem-triage` skill (§5.8).
 *
 * The old skill grouped by the free-text *relevance* string, so the 346-event
 * "Conformance / schema validation" bucket became one mega-task, hard-coded its titles and
 * blockers, and correlated to local PRs by keyword overlap that included `arazzo` (which matches
 * everything). This class instead:
 *
 *  - groups by **roadmap ref** (`roadmapRefs`), one task per ref (§5.8), with an
 *    `unmapped:<topic|source>` bucket so unmapped events never collapse into one giant task;
 *  - takes task titles from `topics.json` (`roadmaps`/`topics`) and `watchlist-status.json`,
 *    never from a hard-coded string;
 *  - correlates to local work via explicit `ourIssue` links and `decisions.json`, falling back
 *    to keyword overlap that **excludes** `arazzo`, `spec`, `openapi`;
 *  - drops events already settled in `decisions.json` so reviewed items do not resurface.
 */
final class Triage
{
    /** Terms too generic to prove a correlation — they match nearly every PR in this repo. */
    public const CORRELATION_STOP_WORDS = ['arazzo', 'spec', 'openapi'];

    /** Statuses in `decisions.json` that retire an item from triage. */
    public const SETTLED_STATUSES = ['reviewed', 'dismissed', 'linked'];

    /**
     * Drop events settled in `decisions.json` (by event `id` or `entityId`).
     *
     * @param  array<int,array<string,mixed>>  $events
     * @param  array<int,array<string,mixed>>  $decisions
     * @return array<int,array<string,mixed>>
     */
    public static function filterDecided(array $events, array $decisions): array
    {
        $settled = [];
        foreach ($decisions as $decision) {
            if (!is_array($decision) || !in_array((string) ($decision['status'] ?? ''), self::SETTLED_STATUSES, true)) {
                continue;
            }
            foreach (['entityId', 'id'] as $key) {
                $value = (string) ($decision[$key] ?? '');
                if ($value !== '') {
                    $settled[$value] = true;
                }
            }
        }

        return array_values(array_filter($events, static function ($event) use ($settled): bool {
            if (!is_array($event)) {
                return false;
            }

            return !isset($settled[(string) ($event['entityId'] ?? '')])
                && !isset($settled[(string) ($event['id'] ?? '')]);
        }));
    }

    /**
     * The grouping key for an event: its first roadmap ref, else an `unmapped:` bucket keyed by
     * topic (then source) so unmapped events do not coalesce.
     *
     * @param  array<string,mixed>  $event
     */
    public static function groupKey(array $event): string
    {
        $refs = self::stringList($event['roadmapRefs'] ?? []);
        if ($refs !== []) {
            return $refs[0];
        }

        $topics = self::stringList($event['topics'] ?? []);
        if ($topics !== []) {
            return 'unmapped:'.$topics[0];
        }

        return 'unmapped:'.((string) ($event['source'] ?? '') ?: 'unknown');
    }

    /**
     * One task per roadmap ref.
     *
     * @param  array<int,array<string,mixed>>  $events
     * @return array<string,array<int,array<string,mixed>>> ref => events
     */
    public static function groupByRoadmap(array $events): array
    {
        $groups = [];
        $seen = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            foreach (self::refsOf($event) as $ref) {
                $dedupe = $ref.'|'.(string) ($event['id'] ?? ($event['entityId'] ?? ''));
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $groups[$ref][] = $event;
            }
        }

        return $groups;
    }

    /**
     * Every group an event belongs to: each roadmap ref, or the single `unmapped:` fallback.
     *
     * @param  array<string,mixed>  $event
     * @return string[]
     */
    public static function refsOf(array $event): array
    {
        $refs = self::stringList($event['roadmapRefs'] ?? []);

        return $refs !== [] ? $refs : [self::groupKey($event)];
    }

    /**
     * Deterministic task order: breaking first, then highest score, then ref.
     *
     * @param  array<string,array<int,array<string,mixed>>>  $groups
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function sortGroups(array $groups): array
    {
        $keys = array_keys($groups);
        usort($keys, static function (string $a, string $b) use ($groups): int {
            return (self::isBreaking($groups[$b]) <=> self::isBreaking($groups[$a]))
                ?: (self::scoreGroup($groups[$b]) <=> self::scoreGroup($groups[$a]))
                ?: strcmp($a, $b);
        });

        $sorted = [];
        foreach ($keys as $key) {
            $sorted[$key] = $groups[$key];
        }

        return $sorted;
    }

    /**
     * A title for a roadmap-ref group, sourced from authored config:
     * watchlist live title → `topics.json` `roadmaps[ref].title` → the group's topic title →
     * a humanized ref. Never hard-coded.
     *
     * @param  array<int,array<string,mixed>>  $group
     * @param  array<string,mixed>  $topicsConfig  the parsed `topics.json`
     * @param  array<int,array<string,mixed>>  $watchlistStatus  rows from `watchlist-status.json`
     */
    public static function titleFor(string $ref, array $group, array $topicsConfig, array $watchlistStatus): string
    {
        foreach ($watchlistStatus as $row) {
            if (!is_array($row) || !in_array($ref, self::stringList($row['roadmap'] ?? []), true)) {
                continue;
            }
            $title = (string) ($row['title'] ?? '');
            if ($title !== '' && $title !== '—') {
                return $title;
            }
        }

        $roadmaps = $topicsConfig['roadmaps'] ?? [];
        if (is_array($roadmaps) && isset($roadmaps[$ref]) && is_array($roadmaps[$ref])) {
            $title = (string) ($roadmaps[$ref]['title'] ?? '');
            if ($title !== '') {
                return $title;
            }
        }

        foreach (self::groupTopics($group) as $topic) {
            $spec = $topicsConfig['topics'][$topic] ?? null;
            if (is_array($spec) && (string) ($spec['title'] ?? '') !== '') {
                return (string) $spec['title'];
            }
        }

        return self::humanize($ref);
    }

    /**
     * Related local work: explicit `ourIssue` links and `decisions.json` links win; otherwise
     * keyword overlap over the group — excluding `arazzo`, `spec`, `openapi`.
     *
     * @param  array<int,array<string,mixed>>  $group
     * @param  array<int,array<string,mixed>>  $decisions
     * @param  array<int,array<string,mixed>>  $localPrs
     * @param  array<int,array<string,mixed>>  $watchlistItems
     * @return array<int,array{ref:string,why:string}> e.g. `[['ref'=>'#151','why'=>'watchlist ourIssue']]`
     */
    public static function relatedFor(string $ref, array $group, array $decisions, array $localPrs, array $watchlistItems): array
    {
        $out = [];
        $entityIds = [];
        foreach ($group as $event) {
            if (is_array($event)) {
                $entityIds[(string) ($event['entityId'] ?? '')] = true;
            }
        }

        // 1. Explicit watchlist `ourIssue` links (the strongest signal).
        foreach ($watchlistItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $ourIssue = $item['ourIssue'] ?? null;
            if ($ourIssue === null || $ourIssue === '' || (int) $ourIssue <= 0) {
                continue;
            }
            $sameRef = in_array($ref, self::stringList($item['roadmap'] ?? []), true);
            $sameEntity = isset($entityIds[self::itemEntityId((string) ($item['ref'] ?? ''))]);
            if ($sameRef || $sameEntity) {
                $out[] = ['ref' => '#'.(int) $ourIssue, 'why' => 'watchlist ourIssue'];
            }
        }

        // 2. Decisions recorded as `linked`.
        foreach ($decisions as $decision) {
            if (!is_array($decision) || (string) ($decision['status'] ?? '') !== 'linked') {
                continue;
            }
            if (!isset($entityIds[(string) ($decision['entityId'] ?? '')])) {
                continue;
            }
            $link = (string) ($decision['issue'] ?? $decision['reason'] ?? '');
            if (preg_match('/#?(\d+)/', $link, $m) === 1) {
                $out[] = ['ref' => '#'.(int) $m[1], 'why' => 'decision'];
            }
        }

        $seen = [];
        $unique = [];
        foreach ($out as $hit) {
            if (isset($seen[$hit['ref']])) {
                continue;
            }
            $seen[$hit['ref']] = true;
            $unique[] = $hit;
        }

        if ($unique !== []) {
            return $unique;
        }

        // 3. Fallback keyword overlap — generic words excluded.
        $keywords = self::keywords(self::groupText($group));
        foreach ($localPrs as $pr) {
            if (!is_array($pr)) {
                continue;
            }
            $prKeywords = self::keywords(implode(' ', [
                (string) ($pr['title'] ?? ''),
                (string) ($pr['body'] ?? ''),
                implode(' ', array_map(
                    static fn ($label): string => is_array($label) ? (string) ($label['name'] ?? '') : (string) $label,
                    (array) ($pr['labels'] ?? []),
                )),
            ]));
            $shared = array_values(array_intersect($keywords, $prKeywords));
            if ($shared === []) {
                continue;
            }
            $out[] = [
                'ref' => '#'.(int) ($pr['number'] ?? 0),
                'why' => 'keyword: '.implode(',', array_slice($shared, 0, 3)),
            ];
        }

        return $out;
    }

    public static function scoreGroup(array $group): int
    {
        $score = 0;
        foreach ($group as $event) {
            if (is_array($event)) {
                $score = max($score, (int) ($event['score'] ?? 0));
            }
        }

        return $score;
    }

    public static function isBreaking(array $group): int
    {
        foreach ($group as $event) {
            if (is_array($event) && ($event['breaking'] ?? false) === true) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Keyword tokens for correlation, with the generic stop words removed.
     *
     * @return string[]
     */
    public static function keywords(string $text): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];
        $stop = array_merge(self::CORRELATION_STOP_WORDS, [
            'the', 'and', 'for', 'with', 'from', 'that', 'this', 'into', 'over', 'when', 'then',
            'add', 'adds', 'via', 'type', 'types', 'new', 'use', 'using', 'support',
            // Conventional-commit prefixes: too generic to prove a correlation.
            'feat', 'fix', 'chore', 'docs', 'style', 'refactor', 'perf', 'test', 'build', 'ci', 'revert',
        ]);
        $stop = array_flip($stop);
        $out = [];
        foreach ($tokens as $token) {
            if (strlen($token) < 3 || isset($stop[$token]) || ctype_digit($token)) {
                continue;
            }
            $out[$token] = true;
        }

        return array_keys($out);
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<int,array<string,mixed>>  $group
     * @return string[]
     */
    private static function groupTopics(array $group): array
    {
        $topics = [];
        foreach ($group as $event) {
            if (!is_array($event)) {
                continue;
            }
            foreach (self::stringList($event['topics'] ?? []) as $topic) {
                $topics[$topic] = true;
            }
        }

        return array_keys($topics);
    }

    /**
     * @param  array<int,array<string,mixed>>  $group
     */
    private static function groupText(array $group): string
    {
        $bits = [];
        foreach ($group as $event) {
            if (!is_array($event)) {
                continue;
            }
            $subject = is_array($event['subject'] ?? null) ? $event['subject'] : [];
            $bits[] = (string) ($subject['title'] ?? '');
            $bits[] = implode(' ', self::stringList($event['topics'] ?? []));
        }

        return implode(' ', $bits);
    }

    /** `OAI/Arazzo-Specification#533` → the entity id the feed uses. */
    private static function itemEntityId(string $ref): string
    {
        if (preg_match('~^([\w.-]+/[\w.-]+)#(\d+)$~', $ref, $m) === 1) {
            return 'gh:'.$m[1].'#'.$m[2];
        }

        return '';
    }

    /** `plan:phase-f-protocol-packages` → `Phase f protocol packages`. */
    private static function humanize(string $ref): string
    {
        if (str_starts_with($ref, 'unmapped:')) {
            $ref = substr($ref, strlen('unmapped:'));
        } elseif (preg_match('/^(plan|spec):(.+)$/', $ref, $m) === 1) {
            $ref = $m[2];
        }
        $words = preg_split('/[-_ ]+/', $ref) ?: [$ref];
        $words = array_values(array_filter($words, static fn (string $w): bool => $w !== ''));

        return ucfirst(implode(' ', $words));
    }

    /**
     * @param  array<int,mixed>  $values
     * @return string[]
     */
    private static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $list = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $list[] = (string) $value;
            }
        }

        return array_values(array_unique($list));
    }
}
