#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL);

require dirname(__DIR__, 4) . '/scripts/ecosystem/FeedEvent.php';
require dirname(__DIR__, 4) . '/scripts/ecosystem/RelevanceMapper.php';
require dirname(__DIR__, 4) . '/scripts/ecosystem/Normalizer.php';

use Ecosystem\RelevanceMapper;

$root = dirname(__DIR__, 4);
$opts = getopt('', ['since:', 'json', 'limit:', 'verbose', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php .agents/skills/ecosystem-triage/scripts/analyze.php [--since=YYYY-MM-DD] [--json] [--limit=20] [--verbose]\n";
    exit(0);
}

$since = $opts['since'] ?? null;
$asJson = isset($opts['json']);
$verbose = isset($opts['verbose']);
$limit = (int) ($opts['limit'] ?? 20);

$feedPath = $root . '/storage/ecosystem-feed/feed.json';
if (! is_file($feedPath)) {
    fwrite(STDERR, "No feed at {$feedPath} — run php scripts/ecosystem/poll.php --fixtures --commit first\n");
    exit(1);
}

$feed = json_decode((string) file_get_contents($feedPath), true) ?? [];
// Filter by since if provided
if ($since !== null) {
    $cut = strtotime($since);
    $feed = array_values(array_filter($feed, fn ($e) => strtotime($e['publishedAt'] ?? '') >= $cut));
}

// Already sorted publishedAt desc by Store; ensure order
usort($feed, fn ($a, $b) => strcmp($b['publishedAt'] ?? '', $a['publishedAt'] ?? ''));

// Gather local PRs/issues via gh when available
$localPrs = [];
$localIssues = [];
$ghAvailable = trim((string) @shell_exec('gh --version 2>&1')) !== '' && str_contains((string) @shell_exec('gh --version 2>&1'), 'gh version');
if ($ghAvailable) {
    $prJson = @shell_exec('gh pr list --state open --limit 100 --json number,title,body,labels,url 2>&1');
    if (is_string($prJson) && str_starts_with(ltrim($prJson), '[')) {
        $localPrs = json_decode($prJson, true) ?? [];
    } elseif ($verbose) {
        fwrite(STDERR, "gh pr list failed or not JSON: " . substr((string) $prJson, 0, 200) . "\n");
    }
    $issueJson = @shell_exec('gh issue list --state open --limit 100 --json number,title,body,labels,url 2>&1');
    if (is_string($issueJson) && str_starts_with(ltrim($issueJson), '[')) {
        $localIssues = json_decode($issueJson, true) ?? [];
    }
} elseif ($verbose) {
    fwrite(STDERR, "gh not available — correlation skipped\n");
}

// Group feed events by relevance (or tags fallback) to coalesce tasks
$groups = [];
foreach ($feed as $ev) {
    $rel = $ev['relevance'] ?? RelevanceMapper::map($ev['tags'] ?? []) ?? 'uncategorized';
    // Normalize relevance to short key for grouping: take first segment before " (" or " —"
    $key = strtolower(trim(explode('(', $rel)[0]));
    $key = trim(explode('—', $key)[0]);
    $key = $key !== '' ? $key : 'uncategorized';
    $groups[$key][] = $ev;
}

// Define priority order: P0 > P1 > P2 > uncategorized, breaking > actionable > watch
$priorityRank = function (string $key, array $events): int {
    $rel = $events[0]['relevance'] ?? $key;
    $tagsAll = strtolower(implode(',', array_merge(...array_map(fn ($e) => $e['tags'] ?? [], $events))) . ' ' . strtolower($rel . ' ' . $key));
    $hasP0 = str_contains($tagsAll, 'p0-') || str_contains($tagsAll, 'wsdl') || str_contains($tagsAll, 'soap') || str_contains($tagsAll, 'source routing');
    $hasP1 = str_contains($tagsAll, 'p1-') || str_contains($tagsAll, 'xpath') || str_contains($tagsAll, 'xml') || str_contains($tagsAll, 'criterion');
    $hasP2 = str_contains($tagsAll, 'p2-') || str_contains($tagsAll, 'mcp') || str_contains($tagsAll, 'cli') && ! $hasP0;
    $sev = 'watch';
    foreach ($events as $ev) {
        if (($ev['severity'] ?? '') === 'breaking') {
            $sev = 'breaking';

            break;
        }
        if (($ev['severity'] ?? '') === 'actionable' && $sev !== 'breaking') {
            $sev = 'actionable';
        }
    }
    $sevRank = $sev === 'breaking' ? 0 : ($sev === 'actionable' ? 1 : 2);
    $tierRank = $hasP0 ? 0 : ($hasP1 ? 1 : ($hasP2 ? 2 : 3));

    return $tierRank * 10 + $sevRank;
};

// Sort groups by priority
uksort($groups, function ($a, $b) use ($groups, $priorityRank) {
    return $priorityRank($a, $groups[$a]) <=> $priorityRank($b, $groups[$b]);
});

// Build tasks - one per group, up to limit
$tasks = [];
$idx = 0;
foreach ($groups as $key => $events) {
    if (count($tasks) >= $limit) {
        break;
    }
    $idx++;
    // Derive title from relevance or most recent event
    $rel = $events[0]['relevance'] ?? $key;
    $relShort = $rel !== '' ? $rel : $key;
    // Title heuristic
    $title = ucfirst($relShort);
    if (str_contains($key, 'wsdl') || str_contains($key, 'soap')) {
        $title = 'Support wsdl sourceDescription type + operationId reuse (SOAP)';
    } elseif (str_contains($key, 'xpath') || str_contains($key, 'xml')) {
        $title = 'Support application/xml payload + XPath targetSelectorType';
    } elseif (str_contains($key, 'mcp')) {
        $title = 'Expose workflows as MCP server tools (P2-2)';
    } elseif (str_contains($key, 'cli')) {
        $title = 'Ship bin/arazzo CLI (validate/run/list) (P2-1)';
    } elseif (str_contains($key, 'actor') || str_contains($key, 'human')) {
        $title = 'Design actor-in-loop (human) suspension for workflows';
    } elseif (str_contains($key, 'loop')) {
        $title = 'Support workflow loops / iteration (replace goto chains)';
    } elseif ($relShort === 'uncategorized') {
        $title = $events[0]['title'] ?? 'Uncategorized feed group';
    }

    // Severity for task is max of group
    $sev = 'watch';
    foreach ($events as $ev) {
        if (($ev['severity'] ?? '') === 'breaking') {
            $sev = 'breaking';

            break;
        }
        if (($ev['severity'] ?? '') === 'actionable') {
            $sev = 'actionable';
        }
    }

    // Blocked by: P0 before P1 before P2
    $blockedBy = 'None — can start immediately';
    if ($idx > 1) {
        // Find prior group that is P0 if current is P1/P2
        $isP2 = str_contains($key, 'p2') || str_contains($key, 'mcp') || str_contains($key, 'cli');
        $isP1 = str_contains($key, 'p1') || str_contains($key, 'xpath');
        if ($isP2 || $isP1) {
            $blockedBy = '01 — Support wsdl source type (P0-6) — complete P0 migration first';
        }
    }
    // Specific ordering: wsdl before soap fault, xml before xpath
    if (str_contains($key, 'soap fault')) {
        $blockedBy = 'wsdl source type task';
    }

    // Correlate to local PRs
    $related = [];
    $haystackForTask = strtolower(implode(' ', [
        $key, $relShort, $title,
        implode(' ', array_column($events, 'title')),
        implode(' ', array_map(fn ($e) => implode(',', $e['tags'] ?? []), $events)),
    ]));
    foreach ($localPrs as $pr) {
        $prText = strtolower(implode(' ', [
            $pr['title'] ?? '', $pr['body'] ?? '',
            implode(' ', array_column($pr['labels'] ?? [], 'name')),
        ]));
        // Simple overlap: count shared keywords
        $keywords = ['wsdl', 'soap', 'xml', 'xpath', 'mcp', 'cli', 'actor', 'human', 'loop', 'criterion', 'speclynx', 'arazzo'];
        $score = 0;
        foreach ($keywords as $kw) {
            if (str_contains($haystackForTask, $kw) && str_contains($prText, $kw)) {
                $score++;
            }
        }
        // Also check relevance substring
        if ($relShort !== '' && str_contains($prText, strtolower(substr($relShort, 0, 8)))) {
            $score += 2;
        }
        if ($score >= 2) {
            $related[] = $pr;
        }
    }

    $relatedStr = 'None — new work';
    if ($related !== []) {
        $bits = [];
        foreach (array_slice($related, 0, 3) as $pr) {
            $bits[] = sprintf('#%s %s %s', $pr['number'] ?? '?', $pr['title'] ?? '', $pr['url'] ?? '');
        }
        $relatedStr = implode('; ', $bits);
    } elseif (! $ghAvailable) {
        $relatedStr = '(gh not available — run with auth to correlate)';
    } elseif ($verbose) {
        $relatedStr = 'None — new work (checked ' . count($localPrs) . ' open PRs)';
    }

    // Acceptance heuristic (check tags + relevance, key stripped wsdl inside parens)
    $tagsAllForAccept = strtolower(implode(',', array_merge(...array_map(fn ($e) => $e['tags'] ?? [], $events))) . ' ' . strtolower(($events[0]['relevance'] ?? '') . ' ' . $key . ' ' . $title));
    $acceptance = [];
    if (str_contains($tagsAllForAccept, 'wsdl') || str_contains($tagsAllForAccept, 'soap')) {
        $acceptance = [
            'gh api shows wsdl type accepted (openapi-or-wsdl-step-object validated)',
            'packages/core/tests/Parser/WsdlSourceTest.php green',
            'Feed events for soap/wsdl marked reviewed in scratch file',
        ];
    } elseif (str_contains($tagsAllForAccept, 'xml') || str_contains($tagsAllForAccept, 'xpath')) {
        $acceptance = [
            'application/xml payload with XPath targetSelectorType handled (spec 5.8.15)',
            'Pest test for XmlPayloadTest green, criterion xpath evaluated',
            'Conformance matrix still green',
        ];
    } elseif (str_contains($tagsAllForAccept, 'mcp')) {
        $acceptance = [
            'MCP server exposes list/describe workflows (read-only) via gh api',
            'No breaking change to existing runner',
        ];
    } else {
        $acceptance = [
            'Feed events in group reviewed and linked in task',
            'Related PR correlation checked via gh pr list',
        ];
    }

    $tasks[] = [
        'num' => sprintf('%02d', $idx),
        'title' => $title,
        'key' => $key,
        'relevance' => $relShort,
        'severity' => $sev,
        'blocked_by' => $blockedBy,
        'related_prs' => $relatedStr,
        'related_prs_raw' => $related,
        'events' => $events,
        'acceptance' => $acceptance,
    ];
}

// Output
if ($asJson) {
    echo json_encode(['tasks' => $tasks, 'prs' => $localPrs, 'issues' => $localIssues, 'feed_count' => count($feed)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

// Markdown to stdout + file
$date = date('Y-m-d');
$outDir = $root . '/.scratch/ecosystem-triage';
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$outPath = $outDir . '/' . $date . '.md';

$md = "# Ecosystem Triage — {$date}\n\n";
$md .= "> Feed: `storage/ecosystem-feed/feed.json` (" . count($feed) . " events) → " . count($tasks) . " tasks (limit {$limit}" . ($since ? ", since {$since}" : '') . ")\n";
$md .= "> Local: " . count($localPrs) . " open PRs, " . count($localIssues) . " open issues via `gh` " . ($ghAvailable ? '(correlated)' : '(gh unavailable)') . "\n";
$md .= "> Plan: `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md` | Relevance: `scripts/ecosystem/RelevanceMapper.php`\n\n";
$md .= "| # | Task | Severity | Relevance | Blocked by | Related PRs |\n";
$md .= "|---|---|---|---|---|---|\n";
foreach ($tasks as $t) {
    $rel = str_replace('|', '\\|', $t['relevance']);
    $blocked = str_replace('|', '\\|', $t['blocked_by']);
    $relatedShort = str_replace('|', '\\|', mb_substr($t['related_prs'], 0, 60));
    $md .= sprintf("| %s | %s | %s | %s | %s | %s |\n", $t['num'], str_replace('|', '\\|', $t['title']), $t['severity'], $rel, $blocked, $relatedShort);
}
$md .= "\n---\n\n";

foreach ($tasks as $t) {
    $md .= "## {$t['num']} — {$t['title']}\n\n";
    $md .= "**Severity:** {$t['severity']} | **Relevance:** {$t['relevance']}\n\n";
    $md .= "**Blocked by:** {$t['blocked_by']}\n\n";
    $md .= "**Related PRs:** {$t['related_prs']}\n\n";
    $md .= "**Source events (" . count($t['events']) . "):**\n";
    foreach (array_slice($t['events'], 0, 5) as $ev) {
        $tags = implode(',', $ev['tags'] ?? []);
        $md .= sprintf("- %s — %s %s — tags: %s — %s\n", $ev['title'] ?? '', $ev['source'] ?? '', $ev['type'] ?? '', $tags, $ev['url'] ?? '');
    }
    if (count($t['events']) > 5) {
        $md .= sprintf("- … and %d more (see feed.json)\n", count($t['events']) - 5);
    }
    $md .= "\n**What to build:** {$t['title']} — see relevance mapping and source events above. Keep vertical slice: parser/validator + runtime + test + docs.\n\n";
    $md .= "**Acceptance:**\n";
    foreach ($t['acceptance'] as $acc) {
        $md .= "- [ ] {$acc}\n";
    }
    $md .= "\n**Out of scope:** adjacent feed groups (other rows in table above).\n\n";
    $md .= "---\n\n";
}

$md .= "## How to publish\n\n";
$md .= "- Review granularity: split `wsdl` vs `soap fault`, merge `xml+xpath` if too fine.\n";
$md .= "- Confirm `Blocked by` — P0 before P1 before P2.\n";
$md .= "- Confirm `Related PRs` — if feed event already has open PR, update that PR instead of duplicating.\n";
$md .= "- Publish: `gh issue create --label ecosystem,ready-for-agent` or `/to-tickets` → `.scratch/ecosystem-triage/issues/NN-*.md`\n";

file_put_contents($outPath, $md);
echo $md;
echo "\nWrote {$outPath}\n";

exit(0);
