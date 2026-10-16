#!/usr/bin/env php
<?php

declare(strict_types=1);

// Watchlist maintenance CLI. The class lives in `Watchlist.php` (capital W) — this tool
// is `watchlist-cli.php` so both coexist on case-insensitive filesystems.
require __DIR__.'/State.php';
require __DIR__.'/Watchlist.php';

use Ecosystem\Watchlist;

$root = dirname(__DIR__, 2);
$watchlistPath = $root.'/config/ecosystem/watchlist.json';
$decisionsPath = $root.'/config/ecosystem/decisions.json';

$args = array_slice($_SERVER['argv'], 1);
$command = array_shift($args) ?? 'list';
$positional = [];
$options = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) {
        $eq = strpos($arg, '=');
        if ($eq !== false) {
            $options[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
        } else {
            $options[substr($arg, 2)] = true;
        }

        continue;
    }
    $positional[] = $arg;
}

if ($command === 'list') {
    foreach (Watchlist::load($watchlistPath) as $item) {
        printf("%-40s %-16s %s\n", $item['ref'], $item['stance'], $item['topic'] ?? '');
    }
    exit(0);
}

if ($command === 'remove') {
    $ref = $positional[0] ?? '';
    if ($ref === '') {
        fwrite(STDERR, "Usage: php scripts/ecosystem/watchlist-cli.php remove <owner/repo#number> --reason=\"...\" [--by=name] [--at=ISO8601]\n");
        exit(1);
    }
    $decision = Watchlist::recordRemoval(
        $watchlistPath,
        $decisionsPath,
        $ref,
        isset($options['reason']) && is_string($options['reason']) ? $options['reason'] : '',
        isset($options['by']) && is_string($options['by']) ? $options['by'] : 'human',
        isset($options['at']) && is_string($options['at']) ? $options['at'] : gmdate('c'),
    );
    echo "Removed {$ref}; recorded decision for {$decision['entityId']}\n";
    exit(0);
}

fwrite(STDERR, "Usage: php scripts/ecosystem/watchlist-cli.php [list | remove <owner/repo#number> --reason=\"...\"]\n");
exit(1);
