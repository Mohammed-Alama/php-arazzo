<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Authored watchlist of tracked upstream items (§5.5).
 *
 * `config/ecosystem/watchlist.json` is the single source of truth: adding an item is a
 * one-line PR (append an object to `items`); removing one records a decision in
 * `config/ecosystem/decisions.json` (see {@see recordRemoval()}). This class is pure —
 * it never fetches; that is {@see Ingestors\WatchlistIngestor}'s job.
 */
final class Watchlist
{
    /** @var string[] */
    public const STANCES = ['shipped', 'plan-to-support', 'evaluate', 'watch', 'declined'];

    /**
     * Load and validate the authored watchlist.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            throw new \RuntimeException("Malformed watchlist config at {$path}");
        }

        $items = [];
        foreach ($data['items'] as $index => $item) {
            if (!is_array($item)) {
                throw new \RuntimeException("watchlist item {$index} must be an object ({$path})");
            }
            if (!isset($item['ref']) || !is_string($item['ref']) || $item['ref'] === '') {
                throw new \RuntimeException("watchlist item {$index} is missing a `ref` string ({$path})");
            }
            self::parseRef($item['ref']);
            if (!isset($item['stance']) || !is_string($item['stance']) || !in_array($item['stance'], self::STANCES, true)) {
                throw new \RuntimeException("watchlist item {$index} has an invalid `stance` ({$path})");
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * `OAI/Arazzo-Specification#533` → `['repo' => 'OAI/Arazzo-Specification', 'number' => 533]`.
     *
     * @return array{repo:string,number:int}
     */
    public static function parseRef(string $ref): array
    {
        if (preg_match('~^([\w.-]+/[\w.-]+)#(\d+)$~', $ref, $m) !== 1) {
            throw new \RuntimeException("Invalid watchlist ref `{$ref}` (expected owner/repo#number)");
        }

        return ['repo' => $m[1], 'number' => (int) $m[2]];
    }

    /** The `entityId` a watchlist item shares with the normal PR/issue feed (§5.3). */
    public static function entityId(string $ref): string
    {
        $parsed = self::parseRef($ref);

        return 'gh:'.$parsed['repo'].'#'.$parsed['number'];
    }

    /** Derive the `owner/repo#number` ref from a normalized observation, or null. */
    public static function observationRef(array $obs): ?string
    {
        $type = (string) ($obs['type'] ?? '');
        if ($type !== 'pr' && $type !== 'issue') {
            return null;
        }
        $source = (string) ($obs['source'] ?? '');
        $externalId = (string) ($obs['externalId'] ?? '');
        if (preg_match('/^(?:pr|issue):(\d+)$/', $externalId, $m) !== 1) {
            return null;
        }

        return $source.'#'.$m[1];
    }

    /**
     * Join the authored items with the live observations of this run (§5.7.1).
     *
     * Every seeded item yields exactly one row; an item that could not be observed this
     * run reports `state = unknown` rather than disappearing.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<int,array<string,mixed>>  $observations
     * @return array<int,array<string,mixed>>
     */
    public static function statusRows(array $items, array $observations): array
    {
        /** @var array<string,array<string,mixed>> $byEntity */
        $byEntity = [];
        foreach ($observations as $obs) {
            if (!is_array($obs)) {
                continue;
            }
            $ref = self::observationRef($obs);
            if ($ref === null) {
                continue;
            }
            $byEntity[self::entityId($ref)] = $obs;
        }

        $rows = [];
        foreach ($items as $item) {
            $ref = (string) $item['ref'];
            $obs = $byEntity[self::entityId($ref)] ?? null;

            $rows[] = [
                'ref' => $ref,
                'topic' => isset($item['topic']) ? (string) $item['topic'] : '',
                'title' => $obs !== null ? (string) ($obs['title'] ?? '—') : '—',
                'state' => $obs !== null ? self::displayState($obs) : 'unknown',
                'base' => $obs !== null ? self::displayBase($obs) : '—',
                'lastChange' => $obs !== null ? self::displayDate($obs) : '—',
                'roadmap' => self::stringList($item['roadmap'] ?? []),
                'ourIssue' => $item['ourIssue'] ?? null,
                'stance' => (string) $item['stance'],
                'url' => $obs !== null ? (string) ($obs['url'] ?? '') : '',
                'live' => $obs !== null,
            ];
        }

        return $rows;
    }

    /** The current lifecycle state of a PR/issue observation. */
    public static function displayState(array $obs): string
    {
        if (($obs['merged'] ?? false) === true) {
            return 'merged';
        }
        if (($obs['state'] ?? '') === 'closed') {
            return 'closed';
        }
        if (($obs['draft'] ?? false) === true) {
            return 'draft';
        }

        return 'open';
    }

    private static function displayBase(array $obs): string
    {
        $base = (string) ($obs['base'] ?? '');

        return $base !== '' ? $base : '—';
    }

    private static function displayDate(array $obs): string
    {
        $stamp = (string) ($obs['lastActivity'] ?? $obs['publishedAt'] ?? '');

        return $stamp !== '' ? substr($stamp, 0, 10) : '—';
    }

    /**
     * A decision row recording that an item left the watchlist (§5.5: removing one
     * records a decision in `decisions.json` rather than silently dropping it).
     *
     * @return array<string,mixed>
     */
    public static function removalDecision(string $ref, string $reason, string $by, string $at): array
    {
        return [
            'entityId' => self::entityId($ref),
            'ref' => $ref,
            'kind' => 'watchlist-removed',
            'status' => 'removed',
            'reason' => $reason,
            'by' => $by,
            'at' => $at,
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    public static function removeItem(array $config, string $ref): array
    {
        $items = [];
        foreach ($config['items'] ?? [] as $item) {
            if (is_array($item) && ($item['ref'] ?? null) === $ref) {
                continue;
            }
            $items[] = $item;
        }
        $config['items'] = array_values($items);

        return $config;
    }

    /**
     * Remove an item from `watchlist.json` and append the removal decision to
     * `decisions.json`. Deterministic: the caller supplies the timestamp.
     *
     * @return array<string,mixed> the recorded decision
     */
    public static function recordRemoval(string $watchlistPath, string $decisionsPath, string $ref, string $reason, string $by, string $at): array
    {
        $config = json_decode((string) file_get_contents($watchlistPath), true);
        if (!is_array($config)) {
            throw new \RuntimeException("Malformed watchlist config at {$watchlistPath}");
        }

        $updated = self::removeItem($config, $ref);
        file_put_contents($watchlistPath, State::prettyJson($updated)."\n");

        $decisions = ['version' => State::SCHEMA_VERSION, 'decisions' => []];
        if (is_file($decisionsPath)) {
            $loaded = json_decode((string) file_get_contents($decisionsPath), true);
            if (is_array($loaded)) {
                $decisions['decisions'] = array_values(array_filter($loaded['decisions'] ?? [], 'is_array'));
            }
        }

        $decision = self::removalDecision($ref, $reason, $by, $at);
        $decisions['decisions'][] = $decision;
        file_put_contents($decisionsPath, State::prettyJson($decisions)."\n");

        return $decision;
    }

    /** @param mixed $values @return string[] */
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

        return array_values($list);
    }
}
