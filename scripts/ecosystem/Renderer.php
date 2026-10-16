<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Bounded, evidence-first digest renderer (§5.7.1, T4).
 *
 * Replaces the old 200-row dashboard. The digest is:
 *  - **Act-complete** — every Act event since the last digest, each with a link and what changed
 *    (no hard ≤15 cap; decision Q5 is "lossless events + Act-complete digest");
 *  - **Watch collapsed** — Watch items live inside a `<details>` block;
 *  - **Noise counted** — one `suppressed:` line; noise is never listed;
 *  - backed by the T5 watchlist status table, a collapsed peer-release inventory and the
 *    meeting agenda.
 *
 * Dates come **only** from the provider `occurredAt`; an event without a real date is dropped
 * rather than stamped with the poll date (F2). The `Generated:` timestamp is rewritten **only
 * when the digest body changes**, so a quiet run (or two back-to-back quiet runs) produces
 * byte-identical output and therefore no commit (A10 / §5.2).
 */
final class Renderer
{
    /** Placeholder standing in for the Generated timestamp while comparing bodies (A10). */
    private const GENERATED_SENTINEL = "\0__GENERATED__\0";

    /**
     * Noise reason (from {@see Scorer}) → the human category counted in `suppressed:`.
     * Declaration order is the reporting order; reasons that share a label are summed.
     *
     * @var array<string,string>
     */
    private const NOISE_CATEGORIES = [
        'bot-author' => 'bot PRs',
        'noise-source' => 'bulk commits',
        'prerelease-tag' => 'prereleases',
        'prerelease-release' => 'prereleases',
        'noise-title' => 'dependency/CI noise',
    ];

    /**
     * Render the digest.
     *
     * @param  array<int,array<string,mixed>>  $events  transition events ({@see Event::toArray()})
     * @param  array<int,array<string,mixed>>  $watchlist  status rows from {@see Watchlist::statusRows()}
     * @param  string  $now  the run clock — only written when the body changes
     * @param  string|null  $previous  the current digest body; its timestamp is reused when nothing changed
     */
    public static function renderDigest(array $events, array $watchlist, string $now, ?string $previous = null): string
    {
        $body = self::body($events, $watchlist);

        if ($previous !== null && self::normalizeGenerated($previous) === $body) {
            return $previous; // byte-identical: no timestamp-only diff, so no commit (A10)
        }

        return str_replace(self::GENERATED_SENTINEL, $now, $body);
    }

    /**
     * The digest body with the Generated timestamp left as a sentinel placeholder.
     *
     * @param  array<int,array<string,mixed>>  $events
     * @param  array<int,array<string,mixed>>  $watchlist
     */
    private static function body(array $events, array $watchlist): string
    {
        $act = [];
        $watch = [];
        $noise = [];
        foreach ($events as $event) {
            if (!is_array($event) || !self::hasRealDate($event)) {
                continue; // never invent a date (F2)
            }
            $tier = (string) ($event['tier'] ?? 'watch');
            if ($tier === 'act') {
                $act[] = $event;
            } elseif ($tier === 'noise') {
                $noise[] = $event;
            } else {
                $watch[] = $event;
            }
        }

        // Meetings get their own one-line section, so don't repeat them in the Watch list.
        $meetings = array_values(array_filter($watch, self::isMeeting(...)));
        $watch = array_values(array_filter($watch, static fn (array $event): bool => !self::isMeeting($event)));
        self::sortByScoreThenDate($act);
        self::sortByScoreThenDate($watch);
        self::sortByDate($meetings);

        $out = "# Ecosystem Feed — Digest\n\n";
        $out .= '> **Generated:** '.self::GENERATED_SENTINEL." by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`\n";
        $out .= "> **Sources:** `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` · **Watchlist:** `config/ecosystem/watchlist.json`\n";
        $out .= "> **Legend:** **Act** = worth a decision · **Watch** = context, collapsed · **Noise** = counted only\n\n";
        $out .= self::renderAct($act);
        $out .= self::renderWatchlist($watchlist);
        $out .= self::renderPeerReleases(self::peerReleases(array_merge($act, $watch)));
        $out .= self::renderMeetings($meetings);
        $out .= self::renderWatch($watch);
        $out .= self::suppressedSummary($noise)."\n";

        return $out;
    }

    // ---------------------------------------------------------------- sections

    /**
     * §5.7.1 "Since last digest → Act": complete, each with a link and what changed.
     *
     * @param  array<int,array<string,mixed>>  $act
     */
    private static function renderAct(array $act): string
    {
        $out = '## Act — since last digest ('.count($act).")\n\n";
        if ($act === []) {
            return $out."_No Act events since the last digest._\n\n";
        }
        foreach ($act as $event) {
            $out .= self::eventBullet($event, (bool) ($event['breaking'] ?? false))."\n";
        }

        return $out."\n";
    }

    /**
     * §5.7.1 "Watch collapsed": every Watch item, inside a collapsed block.
     *
     * @param  array<int,array<string,mixed>>  $watch
     */
    private static function renderWatch(array $watch): string
    {
        $out = '## Watch — '.count($watch)." collapsed\n\n";
        if ($watch === []) {
            return $out."_No Watch items in this window._\n\n";
        }
        $out .= "<details>\n<summary>".count($watch)." topic-matched items — click to expand</summary>\n\n";
        foreach ($watch as $event) {
            $out .= self::eventBullet($event, false)."\n";
        }
        $out .= "\n</details>\n\n";

        return $out;
    }

    /**
     * §5.7.1 "Peer releases (collapsed per library)".
     *
     * @param  array<string,array<int,array<string,mixed>>>  $byLibrary
     */
    private static function renderPeerReleases(array $byLibrary): string
    {
        $out = "## Peer releases\n\n";
        if ($byLibrary === []) {
            return $out."_No peer releases in this window._\n\n";
        }
        foreach ($byLibrary as $library => $releases) {
            $out .= "<details>\n<summary><code>".self::sanitizeCell($library).'</code> — '.count($releases)."</summary>\n\n";
            foreach ($releases as $event) {
                $date = self::dateOf($event);
                $link = self::eventLink($event);
                $out .= "- `{$date}` {$link} — ".self::changeSummary($event)."\n";
            }
            $out .= "\n</details>\n\n";
        }

        return $out;
    }

    /**
     * §5.7.1 "Meeting agenda": one line per `Meeting:` issue.
     *
     * @param  array<int,array<string,mixed>>  $meetings
     */
    private static function renderMeetings(array $meetings): string
    {
        $out = "## Meeting agenda\n\n";
        if ($meetings === []) {
            return $out."_No scheduled meeting items._\n\n";
        }
        foreach ($meetings as $event) {
            $date = self::dateOf($event);
            $link = self::eventLink($event);
            $source = self::sanitizeCell((string) ($event['source'] ?? ''));
            $out .= "- `{$date}` {$link} — `{$source}`\n";
        }

        return $out."\n";
    }

    /**
     * The T5 watchlist status table (§5.7.1). One row per seeded item, always present even when
     * an item could not be observed this run.
     *
     * @param  array<int,array<string,mixed>>  $watchlist
     */
    private static function renderWatchlist(array $watchlist): string
    {
        $out = "## Watchlist — tracked upstream items\n\n";
        if ($watchlist === []) {
            return $out."_No watchlist items configured._\n\n";
        }

        $out .= "| Ref | Title | State | Base | Last change | Roadmap | Our issue | Stance |\n";
        $out .= "|---|---|---|---|---|---|---|---|\n";
        foreach ($watchlist as $row) {
            $ref = self::sanitizeCell((string) ($row['ref'] ?? ''));
            $refCell = $ref !== '' ? '`'.$ref.'`' : '—';
            $title = self::sanitizeCell((string) ($row['title'] ?? ''));
            $titleEsc = str_replace(['[', ']'], ['\\[', '\\]'], $title);
            $url = trim((string) ($row['url'] ?? ''));
            $titleCell = $url !== '' && $title !== '—' ? "[{$titleEsc}]({$url})" : ($title !== '' ? $titleEsc : '—');
            $state = self::sanitizeCell((string) ($row['state'] ?? ''));
            $base = self::sanitizeCell((string) ($row['base'] ?? ''));
            $lastChange = self::sanitizeCell((string) ($row['lastChange'] ?? ''));
            $roadmap = self::sanitizeCell(implode(', ', array_map('strval', is_array($row['roadmap'] ?? null) ? $row['roadmap'] : [])));
            $ourIssue = self::sanitizeCell((string) ($row['ourIssue'] ?? ''));
            $stance = self::sanitizeCell((string) ($row['stance'] ?? ''));
            $out .= "| {$refCell} | {$titleCell} | {$state} | {$base} | {$lastChange} | {$roadmap} | ".($ourIssue !== '' ? $ourIssue : '—')." | {$stance} |\n";
        }

        return $out."\n";
    }

    /**
     * The one-line `suppressed:` summary — every noise category with a non-zero count.
     *
     * @param  array<int,array<string,mixed>>  $noise
     */
    private static function suppressedSummary(array $noise): string
    {
        $counts = [];
        foreach ($noise as $event) {
            $category = self::noiseCategory($event);
            if ($category === null) {
                continue;
            }
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        if ($counts === []) {
            return 'suppressed: none';
        }

        // Stable reporting order: the declared categories first, then anything unexpected.
        $ordered = [];
        foreach (array_values(array_unique(array_values(self::NOISE_CATEGORIES))) as $label) {
            if (($counts[$label] ?? 0) > 0) {
                $ordered[$label] = $counts[$label];
                unset($counts[$label]);
            }
        }
        foreach ($counts as $label => $count) {
            $ordered[$label] = $count;
        }

        return 'suppressed: '.implode(', ', array_map(
            static fn (string $label, int $count): string => $count.' '.self::pluralize($label, $count),
            array_keys($ordered),
            array_values($ordered),
        ));
    }

    /** Singular form of a noise category label, used when the count is one. */
    private static function pluralize(string $label, int $count): string
    {
        if ($count !== 1) {
            return $label;
        }

        return match ($label) {
            'bot PRs' => 'bot PR',
            'bulk commits' => 'bulk commit',
            'prereleases' => 'prerelease',
            default => $label,
        };
    }

    // ---------------------------------------------------------------- item rendering

    /**
     * One Act/Watch bullet: date · link · source · kind · what changed · topics → roadmap.
     *
     * @param  array<string,mixed>  $event
     */
    private static function eventBullet(array $event, bool $breaking): string
    {
        $date = self::dateOf($event);
        $source = self::sanitizeCell((string) ($event['source'] ?? ''));
        $kind = self::sanitizeCell((string) ($event['kind'] ?? ''));
        $bullet = '- `'.$date.'` '.($breaking ? '**breaking** ' : '').self::eventLink($event);
        $bullet .= ' — `'.$source.'` · `'.$kind.'` · '.self::changeSummary($event);

        $topics = self::stringList($event['topics'] ?? []);
        if ($topics !== []) {
            $bullet .= ' · topics '.implode(', ', array_map(static fn (string $topic): string => '`'.self::sanitizeCell($topic).'`', $topics));
        }
        $refs = self::stringList($event['roadmapRefs'] ?? []);
        if ($refs !== []) {
            $bullet .= ' → '.implode(', ', array_map(static fn (string $ref): string => '`'.self::sanitizeCell($ref).'`', $refs));
        }

        return $bullet;
    }

    /**
     * A human description of the transition (`what changed`) — the evidence for the line.
     *
     * @param  array<string,mixed>  $event
     */
    private static function changeSummary(array $event): string
    {
        $kind = (string) ($event['kind'] ?? '');
        $change = is_array($event['change'] ?? null) ? $event['change'] : [];
        $field = (string) ($change['field'] ?? '');
        $from = self::scalarText($change['from'] ?? null);
        $to = self::scalarText($change['to'] ?? null);

        $summary = match ($field) {
            'state' => 'state '.self::transition($from, $to),
            'base' => 'base '.self::transition($from, $to),
            'version' => 'version '.self::transition($from, $to),
            'headSha' => 'headSha '.self::shortSha($from).' → '.self::shortSha($to),
            'hash' => 'hash '.self::shortSha($from).' → '.self::shortSha($to),
            'labels' => self::labelDelta($change),
            default => $kind !== '' ? $kind : 'changed',
        };

        $extras = [];
        $packages = self::stringList($change['packages'] ?? []);
        if ($packages !== []) {
            $extras[] = count($packages).' package'.(count($packages) === 1 ? '' : 's');
        }
        $files = self::stringList($change['files'] ?? []);
        if ($files !== []) {
            $extras[] = count($files).' file'.(count($files) === 1 ? '' : 's');
        }
        $additions = (int) ($change['additions'] ?? 0);
        $deletions = (int) ($change['deletions'] ?? 0);
        if ($additions > 0 || $deletions > 0) {
            $extras[] = '+'.$additions.'/−'.$deletions;
        }

        return $extras === [] ? $summary : $summary.' ('.implode(', ', $extras).')';
    }

    /** `open → merged`, or `→ merged` when there is no previous value. */
    private static function transition(string $from, string $to): string
    {
        $to = $to !== '' ? '`'.$to.'`' : '—';
        if ($from === '') {
            return '→ '.$to;
        }

        return '`'.$from.'` → '.$to;
    }

    /**
     * @param  array<string,mixed>  $change
     */
    private static function labelDelta(array $change): string
    {
        $added = self::stringList($change['added'] ?? []);
        $removed = self::stringList($change['removed'] ?? []);
        $parts = [];
        if ($added !== []) {
            $parts[] = '+'.implode(',', $added);
        }
        if ($removed !== []) {
            $parts[] = '−'.implode(',', $removed);
        }

        return $parts === [] ? 'labels changed' : 'labels '.implode(' ', $parts);
    }

    /**
     * @param  array<string,mixed>  $event
     */
    private static function eventLink(array $event): string
    {
        $subject = is_array($event['subject'] ?? null) ? $event['subject'] : [];
        $title = self::sanitizeCell((string) ($subject['title'] ?? 'untitled'));
        $title = str_replace(['[', ']'], ['\\[', '\\]'], $title);
        $url = trim((string) ($subject['url'] ?? ''));

        return $url !== '' ? "[{$title}]({$url})" : $title;
    }

    // ---------------------------------------------------------------- classification

    /**
     * Release-type events (release/tag/package) grouped by library, newest first.
     *
     * @param  array<int,array<string,mixed>>  $events
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function peerReleases(array $events): array
    {
        $byLibrary = [];
        foreach ($events as $event) {
            if (!self::isRelease($event)) {
                continue;
            }
            $library = (string) ($event['source'] ?? '');
            if ($library === '') {
                continue;
            }
            $byLibrary[$library][] = $event;
        }
        ksort($byLibrary, SORT_STRING);
        foreach ($byLibrary as $library => $releases) {
            self::sortByDate($releases);
            $byLibrary[$library] = $releases;
        }

        return $byLibrary;
    }

    /** @param array<string,mixed> $event */
    private static function isRelease(array $event): bool
    {
        $kind = (string) ($event['kind'] ?? '');

        return in_array(self::subjectType($event), ['release', 'tag', 'package'], true)
            || str_starts_with($kind, 'release.')
            || $kind === 'package.version';
    }

    /** @param array<string,mixed> $event */
    private static function isMeeting(array $event): bool
    {
        $kind = (string) ($event['kind'] ?? '');

        return $kind === 'meeting.agenda'
            || self::subjectType($event) === 'meeting'
            || in_array('meeting-agenda', self::stringList($event['why'] ?? []), true);
    }

    /** @param array<string,mixed> $event */
    private static function subjectType(array $event): string
    {
        $subject = $event['subject'] ?? null;

        return is_array($subject) ? (string) ($subject['type'] ?? '') : '';
    }

    /** @param array<string,mixed> $event */
    private static function noiseCategory(array $event): ?string
    {
        $why = self::stringList($event['why'] ?? []);
        foreach (self::NOISE_CATEGORIES as $reason => $label) {
            if (in_array($reason, $why, true)) {
                return $label;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string,mixed> $event */
    private static function hasRealDate(array $event): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($event['occurredAt'] ?? '')) === 1;
    }

    /** @param array<string,mixed> $event */
    private static function dateOf(array $event): string
    {
        return substr((string) ($event['occurredAt'] ?? ''), 0, 10);
    }

    /**
     * @param  array<int,array<string,mixed>>  $events
     */
    private static function sortByScoreThenDate(array &$events): void
    {
        usort($events, static function (array $a, array $b): int {
            $score = (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);
            if ($score !== 0) {
                return $score;
            }

            return strcmp((string) ($b['occurredAt'] ?? ''), (string) ($a['occurredAt'] ?? ''));
        });
    }

    /**
     * @param  array<int,array<string,mixed>>  $events
     */
    private static function sortByDate(array &$events): void
    {
        usort($events, static fn (array $a, array $b): int => strcmp((string) ($b['occurredAt'] ?? ''), (string) ($a['occurredAt'] ?? '')));
    }

    /** Replace the Generated timestamp value with the comparison sentinel. */
    private static function normalizeGenerated(string $digest): string
    {
        return (string) preg_replace(
            '/^> \*\*Generated:\*\* \S+/m',
            '> **Generated:** '.self::GENERATED_SENTINEL,
            $digest,
        );
    }

    private static function sanitizeCell(string $value): string
    {
        // Collapse all whitespace (including \r\n\t and multiple spaces) to single space, trim, escape pipes
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return str_replace('|', '\\|', $value);
    }

    private static function scalarText(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map('strval', $value));
        }
        if (is_scalar($value)) {
            return self::sanitizeCell((string) $value);
        }

        return '';
    }

    private static function shortSha(string $sha): string
    {
        return $sha !== '' ? substr($sha, 0, 8) : '—';
    }

    /**
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
