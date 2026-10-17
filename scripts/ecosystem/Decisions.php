<?php

declare(strict_types=1);

namespace Ecosystem;

require_once __DIR__.'/State.php';

/**
 * `config/ecosystem/decisions.json` — the durable record of what we decided about a feed item
 * (§5.5, §5.8). Authored by a human (or `analyze.php --mark-reviewed`); the poller only reads it.
 *
 * A decision is keyed by `entityId` (and the `id` it was recorded against), so an item that has
 * been `reviewed` / `dismissed` / `linked` does not resurface on the next triage. Writes are
 * deterministic (sorted rows, stable pretty-printing) so they produce a reviewable diff, and
 * {@see mark()} is idempotent: the same item is never recorded twice.
 */
final class Decisions
{
    public const SCHEMA_VERSION = 1;

    /** Statuses that retire an item from triage. */
    public const SETTLED = ['reviewed', 'dismissed', 'linked'];

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new \RuntimeException("Malformed decisions config at {$path}");
        }
        $rows = $data['decisions'] ?? $data;
        if (!is_array($rows)) {
            throw new \RuntimeException("decisions.json `decisions` must be an array ({$path})");
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * The decision that settles `$id`, or null. `$id` may be an event `id` or an `entityId`.
     *
     * @param  array<int,array<string,mixed>>  $decisions
     * @return array<string,mixed>|null
     */
    public static function settled(array $decisions, string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        foreach ($decisions as $decision) {
            if (!in_array((string) ($decision['status'] ?? ''), self::SETTLED, true)) {
                continue;
            }
            if ((string) ($decision['entityId'] ?? '') === $id || (string) ($decision['id'] ?? '') === $id) {
                return $decision;
            }
        }

        return null;
    }

    public static function isSettled(array $decisions, string $id): bool
    {
        return self::settled($decisions, $id) !== null;
    }

    /**
     * Record a decision against an item. Idempotent for `(entityId, status)`: a repeated mark
     * returns the existing row instead of appending a duplicate.
     *
     * @return array<string,mixed> the recorded (or existing) decision
     */
    public static function mark(
        string $path,
        string $id,
        string $entityId,
        string $status,
        string $by,
        string $at,
        string $reason = '',
    ): array {
        $entityId = $entityId !== '' ? $entityId : $id;
        $rows = self::load($path);
        foreach ($rows as $row) {
            if ((string) ($row['entityId'] ?? '') === $entityId && (string) ($row['status'] ?? '') === $status) {
                return $row;
            }
        }

        $decision = [
            'entityId' => $entityId,
            'id' => $id,
            'kind' => 'triage-'.$status,
            'status' => $status,
            'by' => $by,
            'at' => $at,
        ];
        if ($reason !== '') {
            $decision['reason'] = $reason;
        }

        $rows[] = $decision;
        self::write($path, $rows);

        // Return the row exactly as persisted (keys sorted) so callers can compare identity.
        $normalized = json_decode(State::prettyJson($decision), true);

        return is_array($normalized) ? $normalized : $decision;
    }

    /**
     * `--mark-reviewed <id>`: retire an item so it stops resurfacing.
     *
     * @return array<string,mixed>
     */
    public static function markReviewed(string $path, string $id, string $entityId, string $by, string $at, string $reason = ''): array
    {
        return self::mark($path, $id, $entityId, 'reviewed', $by, $at, $reason);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    public static function write(string $path, array $rows): void
    {
        usort($rows, static fn (array $a, array $b): int => strcmp(State::canonicalJson($a), State::canonicalJson($b)));
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create decisions dir {$dir}");
        }
        file_put_contents($path, State::prettyJson([
            'version' => self::SCHEMA_VERSION,
            'decisions' => array_values($rows),
        ])."\n");
    }
}
