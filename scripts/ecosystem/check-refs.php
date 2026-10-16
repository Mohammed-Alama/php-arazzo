#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * A8 — every `roadmapRefs` key must resolve to an existing superpowers doc/heading (or, for
 * `upstream:`, a well-formed / watchlisted ref). Fails (exit 1) otherwise.
 *
 * Refs are collected from the full authoring surface: every topic in `config/ecosystem/topics.json`,
 * `RelevanceMapper::refs()` (legacy fallback), and `config/ecosystem/watchlist.json` when present.
 *
 * Usage: php scripts/ecosystem/check-refs.php [--json] [--verbose]
 */

require_once __DIR__.'/TopicMatcher.php';
require_once __DIR__.'/RefResolver.php';
require_once __DIR__.'/RelevanceMapper.php';

$root = dirname(__DIR__, 2);

$json = in_array('--json', $argv, true);
$verbose = in_array('--verbose', $argv, true);

/**
 * @return string[]
 */
$collectTopicRefs = static function (string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    $topics = is_array($data) ? ($data['topics'] ?? []) : [];
    $refs = [];
    if (is_array($topics)) {
        foreach ($topics as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            foreach ((array) ($spec['roadmap'] ?? []) as $ref) {
                if (is_string($ref) && $ref !== '') {
                    $refs[$ref] = true;
                }
            }
        }
    }

    return array_keys($refs);
};

$refs = [];
foreach ($collectTopicRefs($root.'/config/ecosystem/topics.json') as $ref) {
    $refs[$ref] = 'topics.json';
}
foreach (RelevanceMapper::refs() as $ref) {
    $refs[$ref] ??= 'RelevanceMapper';
}

$watchlistPath = $root.'/config/ecosystem/watchlist.json';
$hasWatchlist = is_file($watchlistPath);
if ($hasWatchlist) {
    $data = json_decode((string) file_get_contents($watchlistPath), true);
    $items = is_array($data) ? ($data['items'] ?? $data) : [];
    if (is_array($items)) {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach ((array) ($item['roadmap'] ?? []) as $ref) {
                if (is_string($ref) && $ref !== '') {
                    $refs[$ref] ??= 'watchlist.json';
                }
            }
            $target = is_string($item['ref'] ?? null) ? 'upstream:'.$item['ref'] : null;
            if ($target !== null) {
                // Watchlist items themselves are the upstream refs; validate their form too.
                $refs[$target] ??= 'watchlist.json';
            }
        }
    }
}

$resolver = new RefResolver($root);
$results = [];
$failures = [];
foreach (array_keys($refs) as $ref) {
    $result = $resolver->resolve($ref);
    $result['source'] = $refs[$ref];
    $results[] = $result;
    if (!$result['ok']) {
        $failures[] = $result;
    }
}

if ($json) {
    echo json_encode([
        'checked' => count($results),
        'failures' => $failures,
        'watchlist' => $hasWatchlist,
        'results' => $verbose ? $results : null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} else {
    foreach ($results as $result) {
        if (!$result['ok']) {
            echo sprintf("FAIL  %-55s (%s) %s\n", $result['ref'], $result['source'], $result['error']);
        } elseif ($verbose) {
            echo sprintf("ok    %-55s -> %s (%s)\n", $result['ref'], $result['target'] ?? '-', $result['source']);
        }
    }
    echo sprintf(
        "A8: %d refs checked, %d unresolved%s\n",
        count($results),
        count($failures),
        $hasWatchlist ? '' : ' (upstream refs format-validated; no watchlist.json)',
    );
}

exit($failures === [] ? 0 : 1);
