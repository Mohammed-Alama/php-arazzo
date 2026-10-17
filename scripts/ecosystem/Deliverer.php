<?php

declare(strict_types=1);

namespace Ecosystem;

require_once __DIR__.'/Support.php';

/**
 * Delivery surfaces (§5.7.2–§5.7.4, decision Q9).
 *
 * Pure planning logic — it never shells out. Given this run's Act events, the issues that
 * already exist and the authored `config/ecosystem/delivery.json` gate, {@see plan()} returns
 * *what to create*:
 *
 *  - the weekly roll-up issue `ecosystem-digest: YYYY-Www` (label `ecosystem-digest`), which must
 *    never be duplicated for the same ISO week;
 *  - opt-in per-Act auto-issues (default **off**), one per `entityId`, embeddable with a
 *    machine-readable entity marker so a re-run lists them and creates none;
 *  - the optional GitHub Project hooks (only when a Project is configured).
 *
 * The IO side ({@see deliver.php}) lists existing issues via `gh`, calls {@see plan()}, then
 * creates what the plan selects. Keeping creation separate makes the "creates exactly one issue;
 * a re-run creates none" contract a unit test rather than a network call.
 */
final class Deliverer
{
    /** HTML comment embedded in an auto-issue body; the dedupe key read back from `gh issue list`. */
    public const ENTITY_MARKER = 'ecosystem-entity';

    /** Default labels for an auto-issue (§5.7.3). */
    public const AUTO_LABELS = ['ecosystem', 'needs-triage'];

    /** Default label for the weekly roll-up issue (§5.7.2). */
    public const DIGEST_LABEL = 'ecosystem-digest';

    public const DECISION_ACTIONS = ['reviewed', 'dismissed'];

    /**
     * The delivery gate. `autoIssue` defaults to **false** (Q9: start off, enable after a few
     * weeks if precision holds); `project.enabled` defaults to false and is skipped entirely
     * when unset (§5.7.4).
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'version' => 1,
            'weeklyDigest' => true,
            'autoIssue' => false,
            'project' => [
                'enabled' => false,
                'owner' => '',
                'number' => null,
                'upstreamRefField' => 'Upstream ref',
                'status' => 'Needs triage',
            ],
        ];
    }

    public static function defaultConfigPath(): string
    {
        return dirname(__DIR__, 2).'/config/ecosystem/delivery.json';
    }

    /**
     * Load the delivery gate, merged over {@see defaults()}. A missing file is the safe default
     * (digest on, auto-issue off, project off) rather than an error.
     *
     * @return array<string,mixed>
     */
    public static function loadConfig(string $path): array
    {
        if (!is_file($path)) {
            return self::defaults();
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? self::merge(self::defaults(), $data) : self::defaults();
    }

    public static function digestEnabled(array $config): bool
    {
        return ($config['weeklyDigest'] ?? true) !== false;
    }

    public static function autoIssueEnabled(array $config): bool
    {
        return ($config['autoIssue'] ?? false) === true;
    }

    public static function projectEnabled(array $config): bool
    {
        $project = $config['project'] ?? [];

        return is_array($project)
            && ($project['enabled'] ?? false) === true
            && (string) ($project['owner'] ?? '') !== ''
            && (int) ($project['number'] ?? 0) > 0;
    }

    // ---------------------------------------------------------------- ISO week

    /** ISO-8601 week label `YYYY-Www` for a timestamp (UTC). */
    public static function isoWeek(string $now): string
    {
        $ts = strtotime($now);
        if ($ts === false) {
            $ts = time();
        }

        return gmdate('o-\WW', $ts);
    }

    public static function digestTitle(string $now): string
    {
        return 'ecosystem-digest: '.self::isoWeek($now);
    }

    /**
     * Monday 00:00:00Z … next Monday 00:00:00Z (exclusive) for the ISO week of `$now`.
     *
     * @return array{0:string,1:string} [start, end) as `YYYY-MM-DDThh:mm:ssZ`
     */
    public static function weekWindow(string $now): array
    {
        $ts = strtotime($now);
        if ($ts === false) {
            $ts = time();
        }
        $dow = (int) gmdate('N', $ts); // 1 (Mon) … 7 (Sun)
        $monday = $ts - ($dow - 1) * 86400;
        $start = gmdate('Y-m-d', $monday).'T00:00:00Z';
        $end = gmdate('Y-m-d', $monday + 7 * 86400).'T00:00:00Z';

        return [$start, $end];
    }

    /** Whether an event's `occurredAt` falls inside the ISO week of `$now`. */
    public static function inWeek(string $occurredAt, string $now): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $occurredAt) !== 1) {
            return false;
        }
        [$start, $end] = self::weekWindow($now);

        return strcmp($occurredAt, $start) >= 0 && strcmp($occurredAt, $end) < 0;
    }

    // ---------------------------------------------------------------- events

    public static function entityId(array $event): string
    {
        return (string) ($event['entityId'] ?? '');
    }

    public static function eventId(array $event): string
    {
        return (string) ($event['id'] ?? '');
    }

    /** Act events carrying a real provider date (mirrors {@see Renderer}). */
    public static function actEvents(array $events): array
    {
        $act = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            if (($event['tier'] ?? '') !== Scorer::ACT) {
                continue;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($event['occurredAt'] ?? '')) !== 1) {
                continue;
            }
            $act[] = $event;
        }
        usort($act, static function (array $a, array $b): int {
            return strcmp((string) ($b['occurredAt'] ?? ''), (string) ($a['occurredAt'] ?? ''))
                ?: strcmp(self::entityId($a), self::entityId($b))
                ?: strcmp(self::eventId($a), self::eventId($b));
        });

        return $act;
    }

    /** Act events that fall inside the ISO week of `$now` (the weekly digest window). */
    public static function actEventsInWeek(array $events, string $now): array
    {
        return array_values(array_filter(
            self::actEvents($events),
            static fn (array $event): bool => self::inWeek((string) ($event['occurredAt'] ?? ''), $now),
        ));
    }

    /** The `owner/repo#N` upstream ref for a PR/issue entity, or `''`. */
    public static function upstreamRef(array $event): string
    {
        if (preg_match('~^gh:([\w.-]+/[\w.-]+)#(\d+)$~', self::entityId($event), $m) === 1) {
            return $m[1].'#'.$m[2];
        }

        return '';
    }

    // ---------------------------------------------------------------- dedupe

    /** The HTML marker embedded in an auto-issue body for `$entityId`. */
    public static function entityMarker(string $entityId): string
    {
        return '<!-- '.self::ENTITY_MARKER.': '.$entityId.' -->';
    }

    /**
     * Existing issues → `entityId => {number,title,url}` read from body markers.
     *
     * @param  array<int,array<string,mixed>>  $issues  `gh issue list` rows
     * @return array<string,array{number:int,title:string,url:string}>
     */
    public static function existingEntityIssues(array $issues): array
    {
        $map = [];
        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $number = (int) ($issue['number'] ?? 0);
            $body = (string) ($issue['body'] ?? '');
            if ($number <= 0 || preg_match('/<!--\s*'.self::ENTITY_MARKER.':\s*(\S+?)\s*-->/', $body, $m) !== 1) {
                continue;
            }
            $map[$m[1]] = [
                'number' => $number,
                'title' => (string) ($issue['title'] ?? ''),
                'url' => (string) ($issue['url'] ?? ''),
            ];
        }

        return $map;
    }

    /** Whether an issue with this exact title already exists (weekly-digest dedupe). */
    public static function hasIssueTitled(array $issues, string $title): bool
    {
        foreach ($issues as $issue) {
            if (is_array($issue) && (string) ($issue['title'] ?? '') === $title) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- plan

    /**
     * Decide the delivery surfaces for one run.
     *
     * @param  array<int,array<string,mixed>>  $events  this run's (or the week's) transition events
     * @param  array<int,array<string,mixed>>  $existingIssues  `gh issue list` rows (all states)
     * @param  array<string,mixed>  $config  delivery gate
     * @param  string  $now  run clock (UTC)
     * @param  bool  $autoIssueOverride  `--auto-issue` operator opt-in for this run
     * @param  array<string,array{number:int,url?:string}>  $extraEntityIssues  issues created earlier this run
     * @return array{week:string,digest:array{title:string,create:bool,body:string},autoIssues:array<int,array<string,mixed>>,actCount:int}
     */
    public static function plan(
        array $events,
        array $existingIssues,
        array $config,
        string $now,
        bool $autoIssueOverride = false,
        array $extraEntityIssues = [],
    ): array {
        $act = array_values(array_filter(
            self::actEvents($events),
            static fn (array $event): bool => self::inWeek((string) ($event['occurredAt'] ?? ''), $now),
        ));

        $week = self::isoWeek($now);
        $title = 'ecosystem-digest: '.$week;

        $entityIssues = self::existingEntityIssues($existingIssues);
        foreach ($extraEntityIssues as $entityId => $row) {
            $entityIssues[(string) $entityId] = [
                'number' => (int) ($row['number'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
            ];
        }

        $autoEnabled = self::autoIssueEnabled($config) || $autoIssueOverride;
        $autoIssues = self::autoIssues($act, $entityIssues, $autoEnabled);

        $digestCreate = self::digestEnabled($config) && !self::hasIssueTitled($existingIssues, $title);

        return [
            'week' => $week,
            'digest' => [
                'title' => $title,
                'create' => $digestCreate,
                'body' => self::digestBody($week, $act, $entityIssues),
            ],
            'autoIssues' => $autoIssues,
            'actCount' => count($act),
        ];
    }

    /**
     * One auto-issue per Act `entityId`, skipping entities that already have an issue.
     *
     * @param  array<int,array<string,mixed>>  $act
     * @param  array<string,array{number:int,title:string,url:string}>  $entityIssues
     * @return array<int,array<string,mixed>>
     */
    public static function autoIssues(array $act, array $entityIssues, bool $enabled): array
    {
        if (!$enabled) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($act as $event) {
            $entityId = self::entityId($event);
            if ($entityId === '' || isset($entityIssues[$entityId]) || isset($seen[$entityId])) {
                continue;
            }
            $seen[$entityId] = true;
            $out[] = self::autoIssue($event);
        }

        return $out;
    }

    /**
     * Render an auto-issue from an Act event, reusing the `.github/ISSUE_TEMPLATE/ecosystem-update.yml`
     * field set (source · URL · impact · severity) — §5.7.3.
     *
     * @param  array<string,mixed>  $event
     * @return array{entityId:string,title:string,labels:string[],upstreamRef:string,body:string}
     */
    public static function autoIssue(array $event): array
    {
        $entityId = self::entityId($event);
        $severity = ($event['breaking'] ?? false) === true ? 'breaking' : 'actionable';
        $subject = is_array($event['subject'] ?? null) ? $event['subject'] : [];
        $headline = self::oneline((string) ($subject['title'] ?? $entityId));

        return [
            'entityId' => $entityId,
            'title' => 'chore(ecosystem): ['.$severity.'] '.mb_substr($headline, 0, 120),
            'labels' => self::AUTO_LABELS,
            'upstreamRef' => self::upstreamRef($event),
            'body' => self::autoIssueBody($event, $severity),
        ];
    }

    /**
     * The auto-issue body. The entity marker is a machine-readable dedupe key; the sections
     * mirror the issue-template labels.
     *
     * @param  array<string,mixed>  $event
     */
    public static function autoIssueBody(array $event, string $severity): string
    {
        $entityId = self::entityId($event);
        $source = (string) ($event['source'] ?? '');
        $subject = is_array($event['subject'] ?? null) ? $event['subject'] : [];
        $url = (string) ($subject['url'] ?? '');
        $topics = Support::stringList($event['topics'] ?? []);
        $refs = Support::stringList($event['roadmapRefs'] ?? []);
        $why = Support::stringList($event['why'] ?? []);

        $out = self::entityMarker($entityId)."\n\n";
        $out .= "### Source\n\n`{$source}`\n\n";
        $out .= "### Upstream URL\n\n".($url !== '' ? $url : '—')."\n\n";
        $out .= "### Impact on php-arazzo\n\n";
        $out .= '- topics: '.($topics === [] ? '—' : self::inlineCode($topics))."\n";
        $out .= '- roadmap: '.($refs === [] ? '—' : self::inlineCode($refs))."\n";
        $out .= '- why: '.($why === [] ? '—' : implode(', ', array_map(static fn (string $w): string => '`'.$w.'`', $why)))."\n";
        $out .= '- change: '.self::changeSummary($event)."\n\n";
        $out .= "### Severity\n\n{$severity}\n";

        return $out;
    }

    // ---------------------------------------------------------------- digest body

    /**
     * The weekly roll-up body (§5.7.2): every Act item for the week with its upstream link and,
     * when known, the auto-issue number it spawned.
     *
     * @param  array<int,array<string,mixed>>  $act
     * @param  array<string,array{number:int,title?:string,url?:string}>  $entityIssues
     */
    public static function digestBody(string $week, array $act, array $entityIssues = []): string
    {
        $out = '# Ecosystem digest: '.$week."\n\n";
        $out .= 'Act items observed '.$week.' (from the daily ecosystem feed). ';
        $out .= "Tracked source: `docs/ECOSYSTEM_FEED.md`.\n\n";

        if ($act === []) {
            return $out."_No Act items this week._\n";
        }

        foreach ($act as $event) {
            $out .= self::digestBullet($event, $entityIssues)."\n";
        }

        return $out."\n";
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string,array{number:int,title?:string,url?:string}>  $entityIssues
     */
    private static function digestBullet(array $event, array $entityIssues): string
    {
        $date = substr((string) ($event['occurredAt'] ?? ''), 0, 10);
        $subject = is_array($event['subject'] ?? null) ? $event['subject'] : [];
        $title = self::oneline((string) ($subject['title'] ?? self::entityId($event)));
        $url = (string) ($subject['url'] ?? '');
        $breaking = ($event['breaking'] ?? false) === true ? '**breaking** ' : '';

        $bullet = '- `'.$date.'` '.$breaking.($url !== '' ? '['.self::escapeBrackets($title).']('.$url.')' : self::escapeBrackets($title));

        $topics = Support::stringList($event['topics'] ?? []);
        if ($topics !== []) {
            $bullet .= ' · topics '.self::inlineCode($topics);
        }
        $refs = Support::stringList($event['roadmapRefs'] ?? []);
        if ($refs !== []) {
            $bullet .= ' → '.self::inlineCode($refs);
        }
        $entityId = self::entityId($event);
        if ($entityId !== '' && isset($entityIssues[$entityId])) {
            $number = (int) ($entityIssues[$entityId]['number'] ?? 0);
            if ($number > 0) {
                $bullet .= ' · issue #'.$number;
            }
        }

        return $bullet;
    }

    /**
     * @param  array<string,mixed>  $event
     */
    private static function changeSummary(array $event): string
    {
        $change = is_array($event['change'] ?? null) ? $event['change'] : [];
        $field = (string) ($change['field'] ?? '');
        $to = $change['to'] ?? null;
        $toText = is_scalar($to) ? (string) $to : (is_array($to) ? implode(',', array_map('strval', $to)) : '');

        return $field !== '' ? '`'.$field.'` → `'.$toText.'`' : (string) ($event['kind'] ?? 'changed');
    }

    /** @param string[] $values */
    private static function inlineCode(array $values): string
    {
        return implode(', ', array_map(static fn (string $value): string => '`'.self::oneline($value).'`', $values));
    }

    private static function oneline(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function escapeBrackets(string $value): string
    {
        return str_replace(['[', ']'], ['\\[', '\\]'], $value);
    }

    /**
     * Deep-merge a partial config over the defaults (maps merge; lists/scalars replace).
     *
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $override
     * @return array<string,mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
