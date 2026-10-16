<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Durable feed state (§5.2, Q11).
 *
 * Layout under the state dir:
 *   watermarks.json          — per source+endpoint watermarks (provider timestamps only)
 *   entities.jsonl           — one tracked entity per line
 *   events/YYYY-MM.jsonl     — append-only, lossless emitted events (rolled monthly, Q5)
 *
 * A separate human-authored `decisions.json` config is loaded (never bot-written by default).
 *
 * All writes are deterministic: keys are sorted, rows are sorted, and a file is only
 * rewritten when its bytes actually change — so a run with no upstream change produces
 * no diff and therefore no commit (A10 / §5.2 guardrail).
 */
final class State
{
    public const SCHEMA_VERSION = 1;

    /** @var array<string,mixed> */
    private array $watermarks;

    /** @var array<string,array<string,mixed>> entityId => row */
    private array $entities = [];

    /** @var array<int,array<string,mixed>> */
    private array $decisions = [];

    public function __construct(
        private readonly string $stateDir,
        private readonly ?string $decisionsPath = null,
    ) {
        $this->watermarks = $this->loadWatermarks();
        $this->entities = $this->loadEntities();
        $this->decisions = $this->loadDecisions();
    }

    // ---------------------------------------------------------------- watermarks

    /** @return array<string,mixed> */
    public function watermarks(): array
    {
        return $this->watermarks;
    }

    /**
     * Record a provider-derived watermark for a source+endpoint. Never writes a per-run
     * clock: only a watermark that moved is stored, so quiet runs stay byte-identical.
     */
    public function recordWatermark(string $source, string $endpoint, string $field, string $value): void
    {
        if ($source === '' || $endpoint === '' || $field === '' || $value === '') {
            return;
        }

        $current = $this->watermarks['sources'][$source]['endpoints'][$endpoint][$field] ?? null;
        if (is_string($current) && strcmp($current, $value) >= 0) {
            return; // already at or ahead of this value — no churn
        }

        $this->watermarks['sources'][$source]['endpoints'][$endpoint][$field] = $value;
    }

    /** Read a single watermark, or null when unset. */
    public function watermark(string $source, string $endpoint, string $field): ?string
    {
        $value = $this->watermarks['sources'][$source]['endpoints'][$endpoint][$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    // ---------------------------------------------------------------- spec branches

    /**
     * Stored `headSha` per tracked spec branch (§5.2 / §5.4).
     *
     * @return array<string,string> branch => headSha
     */
    public function branchHeads(string $source): array
    {
        $branches = $this->watermarks['sources'][$source]['branches'] ?? [];
        if (!is_array($branches)) {
            return [];
        }

        $out = [];
        foreach ($branches as $branch => $row) {
            $sha = is_array($row) ? ($row['headSha'] ?? null) : null;
            if (is_string($sha) && $sha !== '') {
                $out[(string) $branch] = $sha;
            }
        }

        return $out;
    }

    /**
     * Record a branch head. Only writes when the sha moved, so a quiet run stays byte-identical
     * (no per-run clock, §5.2 guardrail).
     */
    public function recordBranchHead(string $source, string $branch, string $sha): void
    {
        if ($source === '' || $branch === '' || $sha === '') {
            return;
        }
        $current = $this->watermarks['sources'][$source]['branches'][$branch]['headSha'] ?? null;
        if ($current === $sha) {
            return;
        }

        $this->watermarks['sources'][$source]['branches'][$branch]['headSha'] = $sha;
    }

    /**
     * Overwrite a watermark with the value observed this run. Used for `lastTag`,
     * which the caller guarantees is the newest tag (and which cannot be ordered
     * lexically). Byte-stable when the value does not move.
     */
    public function setWatermark(string $source, string $endpoint, string $field, string $value): void
    {
        if ($source === '' || $endpoint === '' || $field === '' || $value === '') {
            return;
        }
        if (($this->watermarks['sources'][$source]['endpoints'][$endpoint][$field] ?? null) === $value) {
            return;
        }

        $this->watermarks['sources'][$source]['endpoints'][$endpoint][$field] = $value;
    }

    /**
     * Record a semver watermark, advancing only when `$version` is newer than the
     * stored one (lexical comparison would rank `1.9.0` above `1.10.0`).
     */
    public function recordVersionWatermark(string $source, string $endpoint, string $field, string $version): void
    {
        if ($source === '' || $endpoint === '' || $field === '' || $version === '') {
            return;
        }

        $current = $this->watermarks['sources'][$source]['endpoints'][$endpoint][$field] ?? null;
        if (is_string($current) && version_compare($current, $version, '>=')) {
            return;
        }

        $this->watermarks['sources'][$source]['endpoints'][$endpoint][$field] = $version;
    }

    /** Date (YYYY-MM-DD) this source was last polled, for `poll: weekly` cadence. */
    public function lastPolledAt(string $source): ?string
    {
        $value = $this->watermarks['sources'][$source]['lastPolledAt'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Record (source-level) that a source was polled on `$date`. Byte-stable when unchanged. */
    public function recordPolledAt(string $source, string $date): void
    {
        if ($source === '' || $date === '') {
            return;
        }
        if (($this->watermarks['sources'][$source]['lastPolledAt'] ?? null) === $date) {
            return;
        }

        $this->watermarks['sources'][$source]['lastPolledAt'] = $date;
    }

    // ---------------------------------------------------------------- entities

    /** @return array<string,array<string,mixed>> entityId => row */
    public function entities(): array
    {
        return $this->entities;
    }

    /** @return array<string,mixed>|null */
    public function entity(string $entityId): ?array
    {
        return $this->entities[$entityId] ?? null;
    }

    /** @param array<string,mixed> $row */
    public function putEntity(array $row): void
    {
        $this->validateEntity($row, 'in-memory', 0);
        $this->entities[(string) $row['entityId']] = $row;
    }

    public function count(): int
    {
        return count($this->entities);
    }

    public function isEmpty(): bool
    {
        return $this->entities === [];
    }

    // ---------------------------------------------------------------- events

    /**
     * Append events to their monthly `events/YYYY-MM.jsonl`, deduplicating by id.
     *
     * @param  Event[]  $events
     * @return int number of events actually appended
     */
    public function appendEvents(array $events): int
    {
        if ($events === []) {
            return 0;
        }

        usort($events, static function (Event $a, Event $b): int {
            return strcmp($a->occurredAt, $b->occurredAt)
                ?: strcmp($a->entityId, $b->entityId)
                ?: strcmp($a->kind, $b->kind)
                ?: strcmp($a->id, $b->id);
        });

        /** @var array<string,Event[]> $byMonth */
        $byMonth = [];
        foreach ($events as $event) {
            $month = substr($event->occurredAt, 0, 7);
            if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
                continue; // §5.3: an event without a provider date is never emitted
            }
            $byMonth[$month][] = $event;
        }
        ksort($byMonth, SORT_STRING);

        $appended = 0;
        foreach ($byMonth as $month => $monthEvents) {
            $path = $this->eventsPath($month);
            $existing = $this->eventIdsIn($path);
            $buffer = '';
            foreach ($monthEvents as $event) {
                if (isset($existing[$event->id])) {
                    continue;
                }
                $existing[$event->id] = true;
                $buffer .= self::canonicalJson($event->toArray())."\n";
                $appended++;
            }
            if ($buffer === '') {
                continue;
            }
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("Cannot create events dir {$dir}");
            }
            file_put_contents($path, $buffer, FILE_APPEND);
        }

        return $appended;
    }

    /** @return array<int,array<string,mixed>> */
    public function eventsForMonth(string $month): array
    {
        $path = $this->eventsPath($month);
        if (!is_file($path)) {
            return [];
        }
        $rows = [];
        foreach (explode("\n", (string) file_get_contents($path)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------- decisions

    /** @return array<int,array<string,mixed>> */
    public function decisions(): array
    {
        return $this->decisions;
    }

    // ---------------------------------------------------------------- persistence

    public function save(): void
    {
        if (!is_dir($this->stateDir) && !mkdir($this->stateDir, 0777, true) && !is_dir($this->stateDir)) {
            throw new \RuntimeException("Cannot create state dir {$this->stateDir}");
        }

        $this->writeIfChanged($this->stateDir.'/watermarks.json', self::prettyJson($this->watermarks)."\n");
        $this->writeIfChanged($this->stateDir.'/entities.jsonl', $this->entitiesJsonl());
    }

    /**
     * Persist decisions. Never called by the poller — `decisions.json` is human-authored
     * (§5.2), so the bot only ever *loads* it.
     */
    public function saveDecisions(): void
    {
        if ($this->decisionsPath === null) {
            return;
        }
        $rows = $this->decisions;
        usort($rows, static fn (array $a, array $b): int => strcmp(self::canonicalJson($a), self::canonicalJson($b)));
        $this->writeIfChanged($this->decisionsPath, self::prettyJson([
            'version' => self::SCHEMA_VERSION,
            'decisions' => array_values($rows),
        ])."\n");
    }

    // ---------------------------------------------------------------- deterministic JSON

    public static function canonicalJson(mixed $value): string
    {
        return self::encode(self::sortKeys($value), 0);
    }

    public static function prettyJson(mixed $value): string
    {
        return self::encode(self::sortKeys($value), JSON_PRETTY_PRINT);
    }

    private static function encode(mixed $value, int $flags): string
    {
        $json = json_encode($value, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }

    /** Recursively sort string-keyed maps; preserve list order. */
    private static function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $child) {
            $out[$key] = self::sortKeys($child);
        }
        if (!array_is_list($out)) {
            ksort($out, SORT_STRING);
        }

        return $out;
    }

    // ---------------------------------------------------------------- loaders

    /** @return array<string,mixed> */
    private function loadWatermarks(): array
    {
        $path = $this->stateDir.'/watermarks.json';
        if (!is_file($path)) {
            return ['version' => self::SCHEMA_VERSION, 'sources' => []];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new \RuntimeException("Malformed watermarks.json at {$path}");
        }
        if (!isset($data['version']) || !is_int($data['version'])) {
            throw new \RuntimeException("watermarks.json is missing an integer `version` ({$path})");
        }
        if (!isset($data['sources']) || !is_array($data['sources'])) {
            throw new \RuntimeException("watermarks.json is missing a `sources` map ({$path})");
        }
        foreach ($data['sources'] as $source => $sections) {
            if (!is_array($sections)) {
                throw new \RuntimeException("watermarks.json sources.{$source} must be an object ({$path})");
            }
        }

        return $data;
    }

    /** @return array<string,array<string,mixed>> */
    private function loadEntities(): array
    {
        $path = $this->stateDir.'/entities.jsonl';
        if (!is_file($path)) {
            return [];
        }

        $rows = [];
        $lineNo = 0;
        foreach (explode("\n", (string) file_get_contents($path)) as $line) {
            $lineNo++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (!is_array($row)) {
                throw new \RuntimeException("Malformed entity at {$path}:{$lineNo}");
            }
            $this->validateEntity($row, $path, $lineNo);
            $rows[(string) $row['entityId']] = $row;
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function loadDecisions(): array
    {
        if ($this->decisionsPath === null || !is_file($this->decisionsPath)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->decisionsPath), true);
        if (!is_array($data)) {
            throw new \RuntimeException("Malformed decisions config at {$this->decisionsPath}");
        }
        if (isset($data['decisions'])) {
            if (!is_array($data['decisions'])) {
                throw new \RuntimeException("decisions.json `decisions` must be an array ({$this->decisionsPath})");
            }

            return array_values(array_filter($data['decisions'], 'is_array'));
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /** @param array<string,mixed> $row */
    private function validateEntity(array $row, string $path, int $line): void
    {
        foreach (['entityId', 'kind', 'source', 'fingerprint', 'fields', 'firstSeenAt', 'lastChangedAt'] as $key) {
            if (!array_key_exists($key, $row)) {
                throw new \RuntimeException("Entity is missing `{$key}` at {$path}:{$line}");
            }
        }
        foreach (['entityId', 'kind', 'source', 'fingerprint', 'firstSeenAt', 'lastChangedAt'] as $key) {
            if (!is_string($row[$key]) || $row[$key] === '') {
                throw new \RuntimeException("Entity `{$key}` must be a non-empty string at {$path}:{$line}");
            }
        }
        if (!is_array($row['fields'])) {
            throw new \RuntimeException("Entity `fields` must be an object at {$path}:{$line}");
        }
    }

    // ---------------------------------------------------------------- helpers

    private function entitiesJsonl(): string
    {
        $rows = array_values($this->entities);
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['entityId'], (string) $b['entityId']));

        $out = '';
        foreach ($rows as $row) {
            $out .= self::canonicalJson($row)."\n";
        }

        return $out;
    }

    private function eventsPath(string $month): string
    {
        return $this->stateDir.'/events/'.$month.'.jsonl';
    }

    /** @return array<string,true> */
    private function eventIdsIn(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $ids = [];
        foreach (explode("\n", (string) file_get_contents($path)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                $ids[$row['id']] = true;
            }
        }

        return $ids;
    }

    private function writeIfChanged(string $path, string $content): void
    {
        if (is_file($path) && file_get_contents($path) === $content) {
            return; // byte-stable: never touch an unchanged file
        }
        file_put_contents($path, $content);
    }
}
