#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL);

// No Composer autoload needed — pure PHP, PSR-0 via require chain.
require __DIR__.'/GhCli.php';
require __DIR__.'/FeedEvent.php';
require __DIR__.'/Event.php';
require __DIR__.'/RelevanceMapper.php';
require __DIR__.'/Normalizer.php';
require __DIR__.'/State.php';
require __DIR__.'/Differ.php';
require __DIR__.'/Cadence.php';
require __DIR__.'/Renderer.php';
require __DIR__.'/Enumerators/OaiOrgEnumerator.php';
require __DIR__.'/Ingestors/GithubApiIngestor.php';
require __DIR__.'/Ingestors/AtomRssIngestor.php';
require __DIR__.'/Ingestors/NpmRegistryIngestor.php';
require __DIR__.'/Ingestors/WebScrapeIngestor.php';

use Ecosystem\Cadence;
use Ecosystem\Differ;
use Ecosystem\Enumerators\OaiOrgEnumerator;
use Ecosystem\Event;
use Ecosystem\GhCli;
use Ecosystem\Ingestors\AtomRssIngestor;
use Ecosystem\Ingestors\GithubApiIngestor;
use Ecosystem\Ingestors\NpmRegistryIngestor;
use Ecosystem\Ingestors\WebScrapeIngestor;
use Ecosystem\Normalizer;
use Ecosystem\Renderer;
use Ecosystem\State;

$root = dirname(__DIR__, 2);
$opts = getopt('', ['dry-run', 'commit', 'since:', 'source:', 'limit:', 'verbose', 'fixtures', 'baseline', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php scripts/ecosystem/poll.php [--dry-run|--commit] [--baseline] [--since=YYYY-MM-DD] [--source=REPO] [--limit=N] [--verbose] [--fixtures]\n";
    echo "  --dry-run : normalize+render without writing state/feed\n";
    echo "  --commit  : write storage/ecosystem-feed/state/* + docs/ECOSYSTEM_FEED.md\n";
    echo "  --baseline: record current entities as state but emit nothing (also automatic on empty state)\n";
    echo "  --since   : filter events publishedAt >= date (post-normalize)\n";
    echo "  --source  : filter to single source id (e.g. OAI/Arazzo-Specification)\n";
    echo "  --limit   : per-endpoint cap (default 20, fixtures mode ignores)\n";
    echo "  --fixtures: use canned payloads (offline, tests) instead of network\n";
    exit(0);
}

$dryRun = isset($opts['dry-run']) || !isset($opts['commit']);
$verbose = isset($opts['verbose']);
$useFixtures = isset($opts['fixtures']);
$since = $opts['since'] ?? null;
$filterSource = $opts['source'] ?? null;
$limit = (int) ($opts['limit'] ?? 20);

$sourcesPath = $root.'/config/ecosystem/sources.json';
$sources = json_decode((string) file_get_contents($sourcesPath), true);
if (!is_array($sources) || !isset($sources['sources'])) {
    fwrite(STDERR, "Invalid config at {$sourcesPath}\n");
    exit(1);
}

$token = getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN') ?: null;
$etagCachePath = $root.'/.cache/ecosystem/etags.json';
$etagCache = [];
if (is_file($etagCachePath)) {
    $etagCache = json_decode((string) file_get_contents($etagCachePath), true) ?? [];
}

// State is loaded before polling so cadence decisions and API watermarks (lastTag,
// npm lastSeenVersion) are available to the ingestors (§5.4).
$stateDir = $root.'/storage/ecosystem-feed/state';
$decisionsPath = $root.'/config/ecosystem/decisions.json';
$state = new State($stateDir, $decisionsPath);
$loadedEntities = $state->count();

// Baseline: the first run on empty state records entities + watermarks but emits nothing,
// so historical releases/tags/PRs are not re-announced as "new" (F2/A2). Also forced by --baseline.
$baseline = isset($opts['baseline']) || $state->isEmpty();

$rawItems = [];

// Lightweight OAI org enumeration: surface new/removed repos as an `org_diff` event.
// New repos are curated into `sources.json` by hand (the enumerator never rewrites it).
if (!$useFixtures) {
    // only in verbose or when no source filter; keeps daily cheap (uses gh cli)
    if ($verbose || $filterSource === null) {
        $enum = OaiOrgEnumerator::fetch($etagCachePath, $token, $verbose);
        $committedPath = $root.'/config/ecosystem/sources.oai.json';
        $committed = json_decode((string) file_get_contents($committedPath), true)['repos'] ?? [];
        $diff = OaiOrgEnumerator::diff($enum['repos'], $committed);
        if ($verbose) {
            echo sprintf("OAI org: live %d, committed %d, added=%s removed=%s cached=%s\n",
                $diff['countLive'], $diff['countCommitted'], json_encode($diff['added']), json_encode($diff['removed']), $enum['cached'] ? 'yes' : 'no');
        }
        if ($diff['added'] !== [] || $diff['removed'] !== []) {
            // emit synthetic event
            $rawItems[] = [
                'source' => 'OAI/org',
                'type' => 'org_diff',
                'externalId' => 'org_diff:'.gmdate('Y-m-d'),
                'title' => 'OAI org diff: +'.count($diff['added']).' -'.count($diff['removed']),
                'url' => 'https://github.com/orgs/OAI/repositories',
                'publishedAt' => gmdate('c'),
                'body' => json_encode($diff),
                'labels' => ['org'],
            ];
        }

        // NOTE: OAI/usearazzo repos are no longer auto-merged into the curated source list.
        // New repos surface as an `org_diff` event and are added to `sources.json` by hand;
        // auto-adding them (and rewriting `sources.json`) only fed non-Arazzo noise into the feed (F9).
    }
}

if ($useFixtures) {
    $fixDir = $root.'/scripts/ecosystem/fixtures';
    if (is_dir($fixDir)) {
        foreach (glob($fixDir.'/*.json') as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                foreach ($data as $row) {
                    $rawItems[] = $row;
                }
            }
        }
    }
    // fallback canned if fixtures dir empty
    if ($rawItems === []) {
        $rawItems = [
            ['source' => 'OAI/Arazzo-Specification', 'type' => 'pr', 'externalId' => 'pr:533', 'title' => 'feat(spec): add SOAP support', 'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533', 'publishedAt' => '2026-07-27T15:46:00Z', 'body' => 'Adds first-class WSDL support via wsdl type, operationId reuse, SOAP Fault detection, XML payloads', 'labels' => ['enhancement'], 'state' => 'open', 'merged' => false],
            ['source' => 'OAI/Arazzo-Specification', 'type' => 'issue', 'externalId' => 'issue:410', 'title' => '1.2 - start of discussion/ideas/breaking changes', 'url' => 'https://github.com/OAI/Arazzo-Specification/issues/410', 'publishedAt' => '2025-11-27T00:00:00Z', 'body' => 'kind discriminator human in the loop mcp transformer function loop goto', 'labels' => [], 'state' => 'open'],
            ['source' => 'usearazzo/arazzo-toolkit', 'type' => 'release', 'externalId' => 'release:v0.2.0', 'title' => 'arazzo-toolkit v0.2.0', 'url' => 'https://github.com/usearazzo/arazzo-toolkit/releases/tag/v0.2.0', 'publishedAt' => gmdate('c'), 'body' => 'parser resolver validator runner', 'labels' => []],
        ];
    }
} else {
    $today = gmdate('Y-m-d');
    foreach ($sources['sources'] as $src) {
        $id = $src['id'] ?? '';
        if ($filterSource !== null && $id !== $filterSource && ($src['repo'] ?? '') !== $filterSource) {
            continue;
        }

        // Cadence (§5.4, F12): honour `poll: daily|weekly` using the stored poll date.
        $poll = (string) ($src['poll'] ?? 'daily');
        if (!Cadence::isDue($poll, $state->lastPolledAt($id), $today)) {
            if ($verbose) {
                echo "Skip {$id} (poll={$poll}, lastPolledAt=".$state->lastPolledAt($id).")\n";
            }

            continue;
        }

        $ingestor = $src['ingestor'] ?? 'github';
        if ($verbose) {
            echo "Poll {$id} via {$ingestor}\n";
        }

        try {
            $watermarks = $state->watermarks();
            $items = match ($ingestor) {
                'github' => GithubApiIngestor::poll($src['repo'], $src['endpoints'] ?? ['releases'], $etagCache, $token, $limit, $verbose, $watermarks),
                'atom' => AtomRssIngestor::poll($src['url'], $id, $limit),
                'npm' => NpmRegistryIngestor::poll($src['package'], $id, $state->watermark($id, 'npm', 'lastSeenVersion')),
                'scrape' => WebScrapeIngestor::poll($src['url'], $id, $src['kind'] ?? 'scrape'),
                default => [],
            };
            foreach ($items as $it) {
                $rawItems[] = $it;
            }

            // Only weekly sources persist a poll date; daily sources must not churn state
            // on every run (§5.2 guardrail / A10).
            if ($poll === 'weekly' && !$dryRun) {
                $state->recordPolledAt($id, $today);
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "  [error] {$id}: ".$e->getMessage()."\n");
        }
        // pacing between sources
        usleep(200000);
    }
}

// persist etag cache
if (!$useFixtures && !$dryRun) {
    $dir = dirname($etagCachePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($etagCachePath, json_encode($etagCache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

$feedEvents = Normalizer::normalizeMany($rawItems);

// filters
if ($since !== null) {
    $sinceTs = strtotime($since);
    $feedEvents = array_values(array_filter($feedEvents, fn ($e) => strtotime($e->publishedAt) >= $sinceTs));
}
if ($filterSource !== null) {
    // after normalize, source may be repo id
    $feedEvents = array_values(array_filter($feedEvents, fn ($e) => $e->source === $filterSource));
}

usort($feedEvents, fn ($a, $b) => strcmp($b->publishedAt, $a->publishedAt));

// Observations carry the normalizer's tags/relevance until the T3 topic scorer replaces
// `RelevanceMapper`; the Differ turns them into transition events (§5.3).
$observations = [];
foreach ($feedEvents as $event) {
    $observations[] = array_merge($event->raw, [
        'tags' => $event->tags,
        'relevance' => $event->relevance,
    ]);
}

$differ = new Differ();
$newEvents = $differ->diff($observations, $state, gmdate('c'), $baseline);

if (!$dryRun) {
    $state->appendEvents($newEvents);
    $state->save();
}

echo sprintf("Collected %d raw -> %d observations (dryRun=%s, fixtures=%s, baseline=%s)\n", count($rawItems), count($observations), $dryRun ? 'yes' : 'no', $useFixtures ? 'yes' : 'no', $baseline ? 'yes' : 'no');
echo sprintf("State: loaded %d entities from %s\n", $loadedEntities, $stateDir);
echo sprintf("Differ: %d new events, %d entities tracked%s\n", count($newEvents), $state->count(), $baseline ? ' (baseline - nothing emitted)' : '');
foreach (array_slice($newEvents, 0, 10) as $event) {
    echo sprintf("  [%s] %s %s %s\n", $event->occurredAt, $event->kind, $event->source, $event->subject['title'] ?? '');
}

// Render the digest from this run's events. It is only rewritten when something actually
// changed (or on the first baseline run) so a quiet run produces no commit (§5.2 guardrail).
$renderRows = array_map(fn (Event $event) => [
    'publishedAt' => $event->occurredAt,
    'source' => $event->source,
    'type' => $event->kind,
    'title' => $event->subject['title'] ?? '',
    'url' => $event->subject['url'] ?? '',
    'tags' => $event->topics,
    'severity' => $event->tier ?? 'watch',
    'relevance' => $event->roadmapRefs[0] ?? null,
], $newEvents);

$digestPath = $root.'/docs/ECOSYSTEM_FEED.md';
$md = Renderer::renderMarkdown(array_values($renderRows), gmdate('c'));
if ($dryRun) {
    echo "\n--- Markdown preview (not written) ---\n";
    echo substr($md, 0, 2000)."\n";
} elseif ($newEvents !== [] || $baseline || !is_file($digestPath)) {
    file_put_contents($digestPath, $md);
    echo "Wrote {$digestPath}\n";
} else {
    echo "Digest unchanged (no events this run) - {$digestPath} left as-is.\n";
}

// A9: log the remaining API budget at the end of the run. The `rate_limit` endpoint
// does not itself count against the limit, so this is cheap and safe.
if (!$useFixtures) {
    $resources = GhCli::rateLimit($verbose);
    if (is_array($resources)) {
        foreach (['core', 'graphql', 'search'] as $bucket) {
            $row = $resources[$bucket] ?? null;
            if (!is_array($row)) {
                continue;
            }
            echo sprintf(
                "X-RateLimit %s: %d/%d remaining (resets %s)\n",
                $bucket,
                (int) ($row['remaining'] ?? 0),
                (int) ($row['limit'] ?? 0),
                (string) ($row['reset'] ?? '?'),
            );
        }
    }
}

exit(0);
