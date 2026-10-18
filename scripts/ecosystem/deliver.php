#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Ecosystem;

require_once __DIR__.'/TopicMatcher.php';
require_once __DIR__.'/Scorer.php';
require_once __DIR__.'/GhCli.php';
require_once __DIR__.'/Deliverer.php';

/**
 * Delivery CLI (§5.7.2–§5.7.4): open the weekly `ecosystem-digest: YYYY-Www` issue and the
 * opt-in per-Act auto-issues, from the committed feed events.
 *
 * Defaults to a dry run — it prints the plan and creates nothing. `--commit` performs the
 * `gh issue create` calls. Dedupe is by digest title and by the `ecosystem-entity` body marker,
 * so re-running in the same week creates nothing new.
 *
 * Usage:
 *   php scripts/ecosystem/deliver.php [--commit] [--json] [--verbose]
 *       [--now=ISO8601] [--events=PATH] [--issues=PATH]
 *       [--auto-issue|--no-auto-issue] [--project|--no-project]
 */
$root = dirname(__DIR__, 2);
$opts = getopt('', [
    'commit', 'dry-run', 'json', 'verbose', 'help',
    'now:', 'events:', 'issues:', 'state:',
    'auto-issue', 'no-auto-issue', 'project', 'no-project',
]);

if (isset($opts['help'])) {
    echo "Usage: php scripts/ecosystem/deliver.php [--commit] [--json] [--verbose]\n";
    echo "  [--now=ISO8601] [--events=PATH] [--issues=PATH] [--state=DIR]\n";
    echo "  [--auto-issue|--no-auto-issue] [--project|--no-project]\n";
    echo "  --commit          create issues (default: dry run)\n";
    echo "  --events=PATH     JSON array of transition events (default: state/events/*.jsonl)\n";
    echo "  --issues=PATH     JSON array of existing issues (default: gh issue list)\n";
    echo "  --auto-issue      opt in to per-Act auto-issues for this run (default: config)\n";
    exit(0);
}

$commit = isset($opts['commit']) && !isset($opts['dry-run']);
$asJson = isset($opts['json']);
$verbose = isset($opts['verbose']);
$now = is_string($opts['now'] ?? null) ? (string) $opts['now'] : gmdate('c');

$config = Deliverer::loadConfig(Deliverer::defaultConfigPath());
$autoOverride = isset($opts['auto-issue']);
if (isset($opts['no-auto-issue'])) {
    $autoOverride = false;
}

// ------------------------------------------------------------------ events
$events = [];
$eventsPath = is_string($opts['events'] ?? null) ? (string) $opts['events'] : null;
if ($eventsPath !== null) {
    $decoded = json_decode((string) @file_get_contents($eventsPath), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Cannot read events fixture at {$eventsPath}\n");
        exit(1);
    }
    $events = $decoded;
} else {
    $stateDir = is_string($opts['state'] ?? null) ? (string) $opts['state'] : $root.'/storage/ecosystem-feed/state';
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

// ------------------------------------------------------------------ existing issues
$existing = [];
$issuesPath = is_string($opts['issues'] ?? null) ? (string) $opts['issues'] : null;
if ($issuesPath !== null) {
    $decoded = json_decode((string) @file_get_contents($issuesPath), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Cannot read issues fixture at {$issuesPath}\n");
        exit(1);
    }
    $existing = $decoded;
} elseif (GhCli::isAvailable()) {
    $existing = GhCli::issueList($verbose);
} elseif ($verbose) {
    fwrite(STDERR, "gh not available — assuming no existing issues\n");
}

// ------------------------------------------------------------------ plan
$plan = Deliverer::plan($events, $existing, $config, $now, $autoOverride);

if (!$commit) {
    if ($asJson) {
        echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    } else {
        echo sprintf("Week %s: %d Act event(s)\n", $plan['week'], $plan['actCount']);
        echo sprintf("  digest issue: %s (%s)\n", $plan['digest']['title'], $plan['digest']['create'] ? 'create' : 'exists');
        echo sprintf("  auto-issues: %d\n", count($plan['autoIssues']));
        foreach ($plan['autoIssues'] as $auto) {
            echo sprintf("    - %s [%s]\n", $auto['title'], $auto['entityId']);
        }
        if ($verbose) {
            echo "\n--- digest body ---\n".$plan['digest']['body'];
        }
    }
    exit(0);
}

// ------------------------------------------------------------------ create
$created = [];
foreach ($plan['autoIssues'] as $auto) {
    $url = GhCli::issueCreate((string) $auto['title'], (string) $auto['body'], (array) $auto['labels'], $verbose);
    $number = GhCli::issueNumberFromUrl((string) $url);
    if ($number <= 0) {
        fwrite(STDERR, "  [error] could not create issue for {$auto['entityId']}\n");

        continue;
    }
    echo sprintf("Created issue #%d for %s\n", $number, $auto['entityId']);
    $created[(string) $auto['entityId']] = ['number' => $number, 'title' => (string) $auto['title'], 'url' => (string) $url];

    // Optional Project hook (§5.7.4): skipped entirely when no Project is configured/exists.
    if (Deliverer::projectEnabled($config)) {
        $project = $config['project'];
        $owner = (string) $project['owner'];
        $projectNumber = (int) $project['number'];
        if (GhCli::projectExists($owner, $projectNumber, $verbose)) {
            GhCli::projectItemAdd($owner, $projectNumber, (string) $url, $verbose);
            // Stamp the `Upstream ref` custom field so the board links back to the source.
            $upstreamRef = (string) ($auto['upstreamRef'] ?? '');
            if ($upstreamRef !== '') {
                GhCli::projectItemEditText(
                    $owner,
                    $projectNumber,
                    (string) $url,
                    (string) ($project['upstreamRefField'] ?? 'Upstream ref'),
                    $upstreamRef,
                    $verbose,
                );
            }
        } elseif ($verbose) {
            echo "  project {$owner}#{$projectNumber} not found — skipping\n";
        }
    }
}

if ($plan['digest']['create']) {
    $entityIssues = Deliverer::existingEntityIssues($existing);
    foreach ($created as $entityId => $row) {
        $entityIssues[(string) $entityId] = ['number' => $row['number'], 'title' => $row['title'], 'url' => $row['url']];
    }
    $body = Deliverer::digestBody($plan['week'], Deliverer::actEventsInWeek($events, $now), $entityIssues);
    $url = GhCli::issueCreate($plan['digest']['title'], $body, [Deliverer::DIGEST_LABEL], $verbose);
    echo $url !== null ? "Created digest issue: {$url}\n" : "::warning::could not create digest issue\n";
} elseif ($verbose) {
    echo "Digest issue for {$plan['week']} already exists — skipping\n";
}

exit(0);
