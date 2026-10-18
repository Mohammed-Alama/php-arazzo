#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Ecosystem;

error_reporting(E_ALL);

/**
 * `ecosystem-triage` analyzer (rewritten per §5.8).
 *
 * Reads the stateful feed events (`storage/ecosystem-feed/state/events/*.jsonl`), the watchlist
 * status table and `config/ecosystem/decisions.json` — never the retired `feed.json` — and groups
 * them by **roadmap ref** into a de-duplicated task list. Titles come from
 * `config/ecosystem/topics.json` / `watchlist-status.json`; there are no hard-coded titles or
 * blockers. `--mark-reviewed <id>` records a decision so the item stops resurfacing.
 *
 * Usage:
 *   php .agents/skills/ecosystem-triage/scripts/analyze.php
 *       [--since=YYYY-MM-DD] [--json] [--limit=20] [--verbose]
 *       [--events=PATH] [--state=DIR] [--watchlist-status=PATH] [--decisions=PATH]
 *       [--mark-reviewed=ID] [--by=ACTOR]
 */
$root = dirname(__DIR__, 4);

require_once $root.'/scripts/ecosystem/State.php';
require_once $root.'/scripts/ecosystem/GhCli.php';
require_once $root.'/scripts/ecosystem/TopicMatcher.php';
require_once $root.'/scripts/ecosystem/Watchlist.php';
require_once $root.'/scripts/ecosystem/Decisions.php';
require_once $root.'/scripts/ecosystem/Triage.php';

$opts = getopt('', [
    'since:', 'json', 'limit:', 'verbose', 'help',
    'events:', 'state:', 'watchlist-status:', 'decisions:',
    'mark-reviewed:', 'by:',
]);

if (isset($opts['help'])) {
    echo "Usage: php analyze.php [--since=YYYY-MM-DD] [--json] [--limit=20] [--verbose]\n";
    echo "       [--events=PATH] [--state=DIR] [--watchlist-status=PATH] [--decisions=PATH]\n";
    echo "       [--mark-reviewed=ID] [--by=ACTOR]\n";
    exit(0);
}

$since = is_string($opts['since'] ?? null) ? (string) $opts['since'] : null;
$asJson = isset($opts['json']);
$verbose = isset($opts['verbose']);
$limit = (int) ($opts['limit'] ?? 20);

$stateDir = is_string($opts['state'] ?? null) ? (string) $opts['state'] : $root.'/storage/ecosystem-feed/state';
$decisionsPath = is_string($opts['decisions'] ?? null) ? (string) $opts['decisions'] : $root.'/config/ecosystem/decisions.json';
$statusPath = is_string($opts['watchlist-status'] ?? null) ? (string) $opts['watchlist-status'] : $stateDir.'/watchlist-status.json';

// ------------------------------------------------------------------ load events
$events = [];
$eventsPath = is_string($opts['events'] ?? null) ? (string) $opts['events'] : null;
if ($eventsPath !== null) {
    $decoded = json_decode((string) @file_get_contents($eventsPath), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "No events at {$eventsPath}\n");
        exit(1);
    }
    $events = array_values(array_filter($decoded, 'is_array'));
} else {
    foreach (glob($stateDir.'/events/*.jsonl') ?: [] as $file) {
        foreach (explode("\n", (string) @file_get_contents($file)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (is_array($row)) {
                $events[] = $row;
            }
        }
    }
}

if ($since !== null) {
    $cut = strtotime($since) ?: 0;
    $events = array_values(array_filter($events, static fn (array $e): bool => (strtotime((string) ($e['occurredAt'] ?? '')) ?: 0) >= $cut));
}

// ------------------------------------------------------------------ mark reviewed
$markId = is_string($opts['mark-reviewed'] ?? null) ? (string) $opts['mark-reviewed'] : null;
if ($markId !== null && $markId !== '') {
    $entityId = $markId;
    foreach ($events as $event) {
        if ((string) ($event['id'] ?? '') === $markId || (string) ($event['entityId'] ?? '') === $markId) {
            $entityId = (string) ($event['entityId'] ?? $markId);
            break;
        }
    }
    $by = is_string($opts['by'] ?? null) && $opts['by'] !== '' ? (string) $opts['by'] : (getenv('GITHUB_ACTOR') ?: 'human');
    $decision = Decisions::markReviewed($decisionsPath, $markId, $entityId, $by, gmdate('c'));

    if ($asJson) {
        echo json_encode($decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    } else {
        echo "Recorded decision: {$decision['status']} {$decision['entityId']} (by {$decision['by']})\n";
    }
    exit(0);
}

// ------------------------------------------------------------------ config + decisions
$topicsConfig = [];
$topicsPath = $root.'/config/ecosystem/topics.json';
if (is_file($topicsPath)) {
    $topicsConfig = json_decode((string) file_get_contents($topicsPath), true) ?? [];
}
$watchlistItems = is_file($root.'/config/ecosystem/watchlist.json') ? Watchlist::load($root.'/config/ecosystem/watchlist.json') : [];

$watchlistStatus = [];
if (is_file($statusPath)) {
    $data = json_decode((string) file_get_contents($statusPath), true);
    if (is_array($data) && is_array($data['items'] ?? null)) {
        $watchlistStatus = $data['items'];
    }
}

$decisions = Decisions::load($decisionsPath);
$events = Triage::filterDecided($events, $decisions);

// ------------------------------------------------------------------ local work (correlation)
$localPrs = [];
if (GhCli::isAvailable()) {
    $prJson = GhCli::api('repos/'.repoSlug($root).'/pulls?state=open&per_page=100', false, $verbose);
    if (is_array($prJson)) {
        foreach ($prJson as $pr) {
            $localPrs[] = [
                'number' => (int) ($pr['number'] ?? 0),
                'title' => (string) ($pr['title'] ?? ''),
                'body' => (string) ($pr['body'] ?? ''),
                'labels' => array_map(static fn ($l): array => ['name' => (string) ($l['name'] ?? '')], (array) ($pr['labels'] ?? [])),
                'url' => (string) ($pr['html_url'] ?? ''),
            ];
        }
    } elseif ($verbose) {
        fwrite(STDERR, "gh pr list failed — correlation falls back to watchlist/decisions only\n");
    }
}

// ------------------------------------------------------------------ group by roadmap ref
$groups = Triage::sortGroups(Triage::groupByRoadmap($events));

$tasks = [];
$idx = 0;
foreach ($groups as $ref => $group) {
    if ($idx >= $limit) {
        break;
    }
    $idx++;
    $related = Triage::relatedFor($ref, $group, $decisions, $localPrs, $watchlistItems);
    $tasks[] = [
        'num' => sprintf('%02d', $idx),
        'ref' => $ref,
        'title' => Triage::titleFor($ref, $group, $topicsConfig, $watchlistStatus),
        'severity' => Triage::isBreaking($group) === 1 ? 'breaking' : 'actionable',
        'related' => $related,
        'events' => $group,
    ];
}

if ($asJson) {
    echo json_encode([
        'tasks' => $tasks,
        'prs' => $localPrs,
        'events' => count($events),
        'groups' => count($groups),
        'decisions' => count($decisions),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    exit(0);
}

// ------------------------------------------------------------------ markdown
$date = gmdate('Y-m-d');
$outDir = $root.'/.scratch/ecosystem-triage';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$outPath = $outDir.'/'.$date.'.md';

$md = "# Ecosystem Triage — {$date}\n\n";
$md .= '> Events: `storage/ecosystem-feed/state/events/*.jsonl` ('.count($events).' open) → '
    .count($groups).' roadmap groups → '.count($tasks)." tasks (limit {$limit}".($since ? ", since {$since}" : '').")\n";
$md .= '> Titles from `config/ecosystem/topics.json` / `watchlist-status.json` · Decisions: `config/ecosystem/decisions.json` ('.count($decisions).")\n";
$md .= '> Related: explicit `ourIssue` + decisions, else keyword overlap excluding `arazzo`/`spec`/`openapi` — '.count($localPrs)." local PRs via `gh`\n\n";
$md .= "| # | Roadmap ref | Task | Severity | Events | Related |\n";
$md .= "|---|---|---|---|---|---|\n";
foreach ($tasks as $t) {
    $relatedShort = $t['related'] === [] ? '—' : implode('; ', array_map(static fn (array $r): string => $r['ref'], $t['related']));
    $md .= sprintf(
        "| %s | `%s` | %s | %s | %d | %s |\n",
        $t['num'],
        escapeCell($t['ref']),
        escapeCell($t['title']),
        $t['severity'],
        count($t['events']),
        escapeCell($relatedShort),
    );
}
$md .= "\n---\n\n";

foreach ($tasks as $t) {
    $md .= "## {$t['num']} — {$t['title']}\n\n";
    $md .= "**Roadmap ref:** `{$t['ref']}`\n\n";
    $md .= "**Severity:** {$t['severity']}\n\n";
    $relatedStr = $t['related'] === [] ? 'None — new work' : implode('; ', array_map(
        static fn (array $r): string => $r['ref'].' ('.$r['why'].')',
        $t['related'],
    ));
    $md .= "**Related:** {$relatedStr}\n\n";
    $md .= '**Source events ('.count($t['events'])."):**\n";
    foreach (array_slice($t['events'], 0, 10) as $ev) {
        $subject = is_array($ev['subject'] ?? null) ? $ev['subject'] : [];
        $md .= sprintf(
            "- `%s` %s — `%s` · `%s`%s\n",
            substr((string) ($ev['occurredAt'] ?? ''), 0, 10),
            (string) ($subject['title'] ?? '(untitled)'),
            (string) ($ev['source'] ?? ''),
            (string) ($ev['kind'] ?? ''),
            (string) ($subject['url'] ?? '') !== '' ? ' '.$subject['url'] : '',
        );
    }
    if (count($t['events']) > 10) {
        $md .= sprintf("- … and %d more (see state/events)\n", count($t['events']) - 10);
    }
    $md .= "\n";
}

$md .= "## Next step — publish\n\n";
$md .= "- One task per roadmap ref; adjust granularity only by editing `topics.json`/`watchlist.json`.\n";
$md .= "- Mark items you have handled: `php analyze.php --mark-reviewed <entityId>` (or the event id).\n";
$md .= "- Publish via `gh issue create --label ecosystem,ready-for-agent` or `/to-tickets`.\n";

file_put_contents($outPath, $md);
echo $md;
echo "\nWrote {$outPath}\n";

exit(0);

/** Escape a markdown table cell. */
function escapeCell(string $value): string
{
    return str_replace('|', '\\|', trim((string) preg_replace('/\s+/u', ' ', $value)));
}

/** Derive `owner/repo` from the git remote, for the local PR correlation. */
function repoSlug(string $root): string
{
    $remote = (string) @shell_exec('git -C '.escapeshellarg($root).' remote get-url origin 2>/dev/null');
    if (preg_match('~[:/]([\w.-]+/[\w.-]+?)(?:\.git)?$~', trim($remote), $m) === 1) {
        return $m[1];
    }

    return 'Mohammed-Alama/php-arazzo';
}
