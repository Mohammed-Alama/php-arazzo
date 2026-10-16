<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Turns observations into transition events against persisted {@see State} (§5.3).
 *
 * Pipeline: observation → entityId + fingerprint → compare with `entities.jsonl` →
 * emit zero or more transition events. The first run over empty state is a *baseline*:
 * it records entities and watermarks but emits nothing, so history is never re-announced
 * as "new" (A2). Replaying the same observations is idempotent — no events on run 2 (A1).
 */
final class Differ
{
    /** @var string[] */
    public const KINDS = [
        'entity.created',
        'pr.state_changed',
        'pr.retargeted',
        'pr.labels_changed',
        'issue.state_changed',
        'release.published',
        'release.prerelease',
        'package.version',
        'spec.text_changed',
        'schema.changed',
        'meeting.agenda',
    ];

    /** @var array{tier:?string,score:?int,why:string[],breaking:bool} scorer output for the current observation */
    private array $scoring = ['tier' => null, 'score' => null, 'why' => [], 'breaking' => false];

    /**
     * @param  array<int,array<string,mixed>>  $observations
     * @return Event[] transition events (empty on baseline or when nothing changed)
     */
    public function diff(array $observations, State $state, string $observedAt, bool $baseline = false): array
    {
        $observations = $this->applyReleaseOverTag($observations);

        /** @var array<string,Event> $events */
        $events = [];
        /** @var array<string,string> $seen latest occurredAt per entityId this run */
        $seen = [];

        foreach ($observations as $observation) {
            $descriptor = $this->observe($observation, $state->entity($this->entityId($observation)));
            if ($descriptor === null) {
                continue;
            }
            $descriptor['observedAt'] = $observedAt;

            $entityId = $descriptor['entityId'];
            if (isset($seen[$entityId]) && strcmp($seen[$entityId], $descriptor['occurredAt']) >= 0) {
                continue; // stale duplicate within a single run
            }
            $seen[$entityId] = $descriptor['occurredAt'];

            $state->recordWatermark($descriptor['source'], $descriptor['endpoint'], 'lastSeenUpdatedAt', $descriptor['occurredAt']);

            $previous = $state->entity($entityId);
            if ($previous === null) {
                if (!$baseline) {
                    foreach ($this->creationEvents($descriptor) as $event) {
                        $events[$event->id] = $event;
                    }
                }
                $state->putEntity($this->newRow($descriptor, $observedAt));

                continue;
            }

            if (State::canonicalJson($previous['fields']) === State::canonicalJson($descriptor['fields'])) {
                continue; // byte-stable: nothing changed, nothing to emit
            }

            foreach ($this->changeEvents($previous['fields'], $descriptor) as $event) {
                $events[$event->id] = $event;
            }
            $state->putEntity($this->updatedRow($previous, $descriptor));
        }

        return array_values($events);
    }

    // ---------------------------------------------------------------- observation -> descriptor

    /**
     * @param  array<string,mixed>  $obs
     * @return array<string,mixed>|null null when the item cannot become an event
     */
    private function observe(array $obs, ?array $previous): ?array
    {
        $source = (string) ($obs['source'] ?? '');
        $type = (string) ($obs['type'] ?? 'unknown');
        $externalId = (string) ($obs['externalId'] ?? '');
        $title = (string) ($obs['title'] ?? $externalId);
        $url = (string) ($obs['url'] ?? '');
        $occurredAt = (string) ($obs['publishedAt'] ?? '');
        if ($occurredAt === '') {
            return null; // §5.3: no event is created without a provider timestamp
        }

        $topics = $this->stringList(is_array($obs['topics'] ?? null) ? $obs['topics'] : (is_array($obs['tags'] ?? null) ? $obs['tags'] : []));
        $roadmapRefs = $this->roadmapRefs($obs);
        $tier = is_string($obs['tier'] ?? null) ? $obs['tier'] : null;
        $score = is_int($obs['score'] ?? null) ? $obs['score'] : null;
        $why = $this->stringList(is_array($obs['why'] ?? null) ? $obs['why'] : []);
        $breaking = ($obs['breaking'] ?? false) === true;
        $previousFields = is_array($previous['fields'] ?? null) ? $previous['fields'] : [];

        $this->scoring = ['tier' => $tier, 'score' => $score, 'why' => $why, 'breaking' => $breaking];

        if (str_starts_with($externalId, 'npm:')) {
            $version = substr($externalId, 4);
            if ($version === '') {
                return null;
            }

            return $this->descriptor('package', 'npm', 'npm:'.$source.'#'.$version, $source, $occurredAt, 'package', $title, $url, [
                'version' => $version,
            ], $topics, $roadmapRefs);
        }

        return match ($type) {
            'pr' => $this->descriptor('pr', 'pulls', 'gh:'.$source.'#'.$this->localId($externalId), $source, $occurredAt, 'pr', $title, $url, [
                'state' => $this->prState($obs, $previousFields),
                'draft' => ($obs['draft'] ?? false) === true,
                'merged' => ($obs['merged'] ?? false) === true,
                'base' => (string) ($obs['base'] ?? ''),
                'labels' => $this->stringList(is_array($obs['labels'] ?? null) ? $obs['labels'] : []),
            ], $topics, $roadmapRefs),
            'issue' => $this->issueDescriptor($obs, $source, $externalId, $occurredAt, $title, $url, $topics, $roadmapRefs),
            'release' => $this->versionDescriptor('release', 'releases', $source, $externalId, $occurredAt, $title, $url, $obs, $topics, $roadmapRefs),
            'tag' => $this->versionDescriptor('tag', 'tags', $source, $externalId, $occurredAt, $title, $url, $obs, $topics, $roadmapRefs),
            'commit' => $this->descriptor('commit', 'commits', 'gh:'.$source.'#commit:'.$this->localId($externalId), $source, $occurredAt, 'commit', $title, $url, [
                'sha' => $this->localId($externalId),
                'title' => $title,
            ], $topics, $roadmapRefs),
            'spec', 'spec_text', 'spec-text' => $this->branchDescriptor('spec', $obs, $source, $externalId, $occurredAt, $title, $url, $topics, $roadmapRefs),
            'schema' => $this->branchDescriptor('schema', $obs, $source, $externalId, $occurredAt, $title, $url, $topics, $roadmapRefs),
            default => $this->genericDescriptor($type, $source, $externalId, $occurredAt, $title, $url, $topics, $roadmapRefs),
        };
    }

    /**
     * @param  array<string,mixed>  $obs
     * @param  string[]  $topics
     * @param  string[]  $roadmapRefs
     * @return array<string,mixed>
     */
    private function issueDescriptor(array $obs, string $source, string $externalId, string $occurredAt, string $title, string $url, array $topics, array $roadmapRefs): array
    {
        $isMeeting = preg_match('/^\s*meeting\b/i', $title) === 1;

        return $this->descriptor($isMeeting ? 'meeting' : 'issue', 'issues', 'gh:'.$source.'#'.$this->localId($externalId), $source, $occurredAt, 'issue', $title, $url, [
            'state' => (string) ($obs['state'] ?? ''),
            'labels' => $this->stringList(is_array($obs['labels'] ?? null) ? $obs['labels'] : []),
        ], $topics, $roadmapRefs);
    }

    /**
     * A release or a tag: same shape, different entity namespace and precedence.
     *
     * @param  array<string,mixed>  $obs
     * @param  string[]  $topics
     * @param  string[]  $roadmapRefs
     * @return array<string,mixed>|null
     */
    private function versionDescriptor(string $kind, string $endpoint, string $source, string $externalId, string $occurredAt, string $title, string $url, array $obs, array $topics, array $roadmapRefs): ?array
    {
        $version = $this->versionOf($externalId);
        if ($version === '') {
            return null;
        }

        $fields = ['version' => $version];
        if ($kind === 'release') {
            $fields['prerelease'] = ($obs['prerelease'] ?? false) === true;
            $fields['draft'] = ($obs['draft'] ?? false) === true;
        }

        return $this->descriptor($kind, $endpoint, 'gh:'.$source.'#'.$kind.':'.$version, $source, $occurredAt, $kind, $title, $url, $fields, $topics, $roadmapRefs);
    }

    /**
     * A spec-text or schema snapshot keyed by branch (T6 feeds the diff payload).
     *
     * @param  array<string,mixed>  $obs
     * @param  string[]  $topics
     * @param  string[]  $roadmapRefs
     * @return array<string,mixed>
     */
    private function branchDescriptor(string $kind, array $obs, string $source, string $externalId, string $occurredAt, string $title, string $url, array $topics, array $roadmapRefs): array
    {
        $branch = (string) ($obs['branch'] ?? $obs['base'] ?? 'main');
        $fileStats = $this->fileRows(is_array($obs['fileStats'] ?? null) ? $obs['fileStats'] : []);
        $fields = [
            'branch' => $branch,
            'headSha' => $kind === 'spec' ? (string) ($obs['headSha'] ?? $obs['sha'] ?? $this->localId($externalId)) : '',
            'hash' => $kind === 'schema' ? (string) ($obs['hash'] ?? $this->localId($externalId)) : '',
            'files' => $this->stringList(is_array($obs['files'] ?? null) ? $obs['files'] : []),
            'fileStats' => $fileStats,
            'commits' => $this->orderedList(is_array($obs['commits'] ?? null) ? $obs['commits'] : []),
            'additions' => $this->sumField($fileStats, 'additions'),
            'deletions' => $this->sumField($fileStats, 'deletions'),
        ];

        return $this->descriptor($kind, 'branches', 'gh:'.$source.'#'.$kind.':'.$branch, $source, $occurredAt, $kind, $title, $url, $fields, $topics, $roadmapRefs);
    }

    /**
     * Unknown kinds still track a fingerprint so upstream change is never silently dropped.
     *
     * @param  string[]  $topics
     * @param  string[]  $roadmapRefs
     * @return array<string,mixed>
     */
    private function genericDescriptor(string $type, string $source, string $externalId, string $occurredAt, string $title, string $url, array $topics, array $roadmapRefs): array
    {
        return $this->descriptor($type, $type, 'feed:'.$source.'#'.$type.':'.$this->genericLocal($externalId, $title), $source, $occurredAt, $type, $title, $url, [
            'title' => $title,
        ], $topics, $roadmapRefs);
    }

    /**
     * @param  string[]  $topics
     * @param  string[]  $roadmapRefs
     * @return array<string,mixed>
     */
    private function descriptor(string $kind, string $endpoint, string $entityId, string $source, string $occurredAt, string $subjectType, string $title, string $url, array $fields, array $topics, array $roadmapRefs): array
    {
        return [
            'kind' => $kind,
            'endpoint' => $endpoint,
            'entityId' => $entityId,
            'source' => $source,
            'occurredAt' => $occurredAt,
            'subjectType' => $subjectType,
            'title' => $title,
            'url' => $url,
            'fields' => $fields,
            'fingerprint' => hash('sha256', State::canonicalJson($fields)),
            'topics' => $topics,
            'roadmapRefs' => $roadmapRefs,
            'tier' => $this->scoring['tier'],
            'score' => $this->scoring['score'],
            'why' => $this->scoring['why'],
            'breaking' => $this->scoring['breaking'],
        ];
    }

    // ---------------------------------------------------------------- events

    /** @return Event[] */
    private function creationEvents(array $d): array
    {
        $kind = match ($d['kind']) {
            'release' => ($d['fields']['prerelease'] ?? false) === true ? 'release.prerelease' : 'release.published',
            'package' => 'package.version',
            'spec' => 'spec.text_changed',
            'schema' => 'schema.changed',
            'meeting' => 'meeting.agenda',
            default => 'entity.created',
        };

        $change = match ($d['kind']) {
            'pr', 'issue', 'meeting' => ['field' => 'state', 'from' => null, 'to' => $d['fields']['state'] ?? null],
            'release', 'package', 'tag' => ['field' => 'version', 'from' => null, 'to' => $d['fields']['version'] ?? null],
            'spec' => ['field' => 'headSha', 'from' => null, 'to' => $d['fields']['headSha'] ?? null],
            'schema' => ['field' => 'hash', 'from' => null, 'to' => $d['fields']['hash'] ?? null],
            default => ['field' => 'entity', 'from' => null, 'to' => null],
        };

        return [$this->event($d, $kind, $change + $this->branchDetail($d['fields'], $d['kind']))];
    }

    /**
     * @param  array<string,mixed>  $previousFields
     * @return Event[]
     */
    private function changeEvents(array $previousFields, array $d): array
    {
        $fields = $d['fields'];
        $events = [];

        switch ($d['kind']) {
            case 'pr':
                if (($previousFields['state'] ?? null) !== $fields['state']) {
                    $events[] = $this->event($d, 'pr.state_changed', [
                        'field' => 'state',
                        'from' => $previousFields['state'] ?? null,
                        'to' => $fields['state'],
                        'base' => $fields['base'],
                    ]);
                }
                if (($previousFields['base'] ?? null) !== $fields['base']) {
                    $events[] = $this->event($d, 'pr.retargeted', [
                        'field' => 'base',
                        'from' => $previousFields['base'] ?? null,
                        'to' => $fields['base'],
                    ]);
                }
                if (($previousFields['labels'] ?? []) !== $fields['labels']) {
                    $from = is_array($previousFields['labels'] ?? null) ? $previousFields['labels'] : [];
                    $to = is_array($fields['labels']) ? $fields['labels'] : [];
                    $events[] = $this->event($d, 'pr.labels_changed', [
                        'field' => 'labels',
                        'from' => array_values($from),
                        'to' => array_values($to),
                        'added' => array_values(array_diff($to, $from)),
                        'removed' => array_values(array_diff($from, $to)),
                    ]);
                }
                break;
            case 'issue':
                if (($previousFields['state'] ?? null) !== $fields['state']) {
                    $events[] = $this->event($d, 'issue.state_changed', [
                        'field' => 'state',
                        'from' => $previousFields['state'] ?? null,
                        'to' => $fields['state'],
                    ]);
                }
                break;
            case 'meeting':
                $events[] = $this->event($d, 'meeting.agenda', [
                    'field' => 'state',
                    'from' => $previousFields['state'] ?? null,
                    'to' => $fields['state'] ?? null,
                ]);
                break;
            case 'release':
                $kind = ($fields['prerelease'] ?? false) === true ? 'release.prerelease' : 'release.published';
                $events[] = $this->event($d, $kind, [
                    'field' => 'version',
                    'from' => $previousFields['version'] ?? null,
                    'to' => $fields['version'] ?? null,
                ]);
                break;
            case 'package':
                $events[] = $this->event($d, 'package.version', [
                    'field' => 'version',
                    'from' => $previousFields['version'] ?? null,
                    'to' => $fields['version'] ?? null,
                ]);
                break;
            case 'spec':
                $events[] = $this->event($d, 'spec.text_changed', [
                    'field' => 'headSha',
                    'from' => $previousFields['headSha'] ?? null,
                    'to' => $fields['headSha'] ?? null,
                ] + $this->branchDetail($fields, 'spec'));
                break;
            case 'schema':
                $events[] = $this->event($d, 'schema.changed', [
                    'field' => 'hash',
                    'from' => $previousFields['hash'] ?? null,
                    'to' => $fields['hash'] ?? null,
                ] + $this->branchDetail($fields, 'schema'));
                break;
            default:
                break; // immutable kinds emit only `entity.created`
        }

        return $events;
    }

    /** @param array<string,mixed> $change */
    private function event(array $d, string $kind, array $change): Event
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \LogicException("Unknown event kind `{$kind}`");
        }

        $to = $change['to'] ?? $d['occurredAt'];
        if ($to === null) {
            $to = $d['occurredAt'];
        }
        if (is_array($to)) {
            $to = implode(',', array_map('strval', $to));
        }

        return new Event(
            Event::makeId($d['entityId'], $kind, (string) $to),
            $d['entityId'],
            $kind,
            $d['source'],
            $d['occurredAt'],
            (string) ($d['observedAt'] ?? $d['occurredAt']),
            [
                'type' => $d['subjectType'],
                'title' => $d['title'],
                'url' => $d['url'],
            ],
            $change,
            $d['topics'],
            $d['roadmapRefs'],
            $d['tier'],
            $d['score'],
            $d['why'],
            ($d['breaking'] ?? false) === true,
        );
    }

    // ---------------------------------------------------------------- rows

    /** @return array<string,mixed> */
    private function newRow(array $d, string $observedAt): array
    {
        return [
            'entityId' => $d['entityId'],
            'kind' => $d['kind'],
            'source' => $d['source'],
            'fingerprint' => $d['fingerprint'],
            'fields' => $d['fields'],
            'firstSeenAt' => $observedAt,
            'lastChangedAt' => $d['occurredAt'],
        ];
    }

    /** @return array<string,mixed> */
    private function updatedRow(array $previous, array $d): array
    {
        return [
            'entityId' => $d['entityId'],
            'kind' => $d['kind'],
            'source' => $d['source'],
            'fingerprint' => $d['fingerprint'],
            'fields' => $d['fields'],
            'firstSeenAt' => (string) $previous['firstSeenAt'],
            'lastChangedAt' => $d['occurredAt'],
        ];
    }

    // ---------------------------------------------------------------- primitives

    /** Derive the PR display state (§5.3: open/draft/ready/merged/closed). */
    private function prState(array $obs, array $previousFields): string
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
        if (($previousFields['state'] ?? null) === 'draft') {
            return 'ready';
        }

        return 'open';
    }

    /**
     * Release-over-tag precedence (§5.4): never emit a tag event for a version that has a
     * release; draft releases are not releases yet.
     *
     * @param  array<int,array<string,mixed>>  $observations
     * @return array<int,array<string,mixed>>
     */
    private function applyReleaseOverTag(array $observations): array
    {
        /** @var array<string,array<string,true>> $releaseVersions */
        $releaseVersions = [];
        foreach ($observations as $observation) {
            if (($observation['type'] ?? '') !== 'release' || str_starts_with((string) ($observation['externalId'] ?? ''), 'npm:')) {
                continue;
            }
            if (($observation['draft'] ?? false) === true) {
                continue;
            }
            $version = $this->versionOf((string) ($observation['externalId'] ?? ''));
            if ($version !== '') {
                $releaseVersions[(string) ($observation['source'] ?? '')][$version] = true;
            }
        }

        $out = [];
        foreach ($observations as $observation) {
            $type = (string) ($observation['type'] ?? '');
            if ($type === 'release' && ($observation['draft'] ?? false) === true) {
                continue; // skip draft releases outright
            }
            if ($type === 'tag') {
                $version = $this->versionOf((string) ($observation['externalId'] ?? ''));
                if ($version !== '' && isset($releaseVersions[(string) ($observation['source'] ?? '')][$version])) {
                    continue; // release wins over tag
                }
            }
            $out[] = $observation;
        }

        return $out;
    }

    /** @param array<string,mixed> $obs */
    private function entityId(array $obs): string
    {
        $source = (string) ($obs['source'] ?? '');
        $type = (string) ($obs['type'] ?? 'unknown');
        $externalId = (string) ($obs['externalId'] ?? '');

        if (str_starts_with($externalId, 'npm:')) {
            return 'npm:'.$source.'#'.substr($externalId, 4);
        }

        return match ($type) {
            'pr', 'issue' => 'gh:'.$source.'#'.$this->localId($externalId),
            'release' => 'gh:'.$source.'#release:'.$this->versionOf($externalId),
            'tag' => 'gh:'.$source.'#tag:'.$this->versionOf($externalId),
            'commit' => 'gh:'.$source.'#commit:'.$this->localId($externalId),
            'spec', 'spec_text', 'spec-text' => 'gh:'.$source.'#spec:'.(string) ($obs['branch'] ?? $obs['base'] ?? 'main'),
            'schema' => 'gh:'.$source.'#schema:'.(string) ($obs['branch'] ?? $obs['base'] ?? 'main'),
            default => 'feed:'.$source.'#'.$type.':'.$this->genericLocal($externalId, (string) ($obs['title'] ?? '')),
        };
    }

    private function genericLocal(string $externalId, string $title): string
    {
        $local = $this->localId($externalId);

        return $local !== '' ? $local : substr(hash('sha256', $title), 0, 12);
    }

    private function localId(string $externalId): string
    {
        foreach (['pr:', 'issue:', 'release:', 'tag:', 'commit:', 'npm:', 'atom:', 'checksum:'] as $prefix) {
            if (str_starts_with($externalId, $prefix)) {
                return substr($externalId, strlen($prefix));
            }
        }

        return $externalId;
    }

    private function versionOf(string $externalId): string
    {
        foreach (['release:', 'tag:'] as $prefix) {
            if (str_starts_with($externalId, $prefix)) {
                return substr($externalId, strlen($prefix));
            }
        }

        return '';
    }

    /**
     * Changed-file payload for a `spec.text_changed` / `schema.changed` transition: the files,
     * their per-file +/- line counts, the commit titles, and the totals (§5.4).
     *
     * @param  array<string,mixed>  $fields
     * @return array<string,mixed>
     */
    private function branchDetail(array $fields, string $kind): array
    {
        if ($kind !== 'spec' && $kind !== 'schema') {
            return [];
        }

        return [
            'files' => is_array($fields['files'] ?? null) ? $fields['files'] : [],
            'fileStats' => is_array($fields['fileStats'] ?? null) ? $fields['fileStats'] : [],
            'commits' => is_array($fields['commits'] ?? null) ? $fields['commits'] : [],
            'additions' => (int) ($fields['additions'] ?? 0),
            'deletions' => (int) ($fields['deletions'] ?? 0),
        ];
    }

    /**
     * Normalize changed-file rows (deterministic order) for the entity fingerprint + event payload.
     *
     * @param  array<int,mixed>  $rows
     * @return array<int,array{path:string,status:string,additions:int,deletions:int,changes:int}>
     */
    private function fileRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $path = (string) ($row['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $out[] = [
                'path' => $path,
                'status' => (string) ($row['status'] ?? ''),
                'additions' => (int) ($row['additions'] ?? 0),
                'deletions' => (int) ($row['deletions'] ?? 0),
                'changes' => (int) ($row['changes'] ?? 0),
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $out;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function sumField(array $rows, string $field): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) ($row[$field] ?? 0);
        }

        return $total;
    }

    /**
     * Deduplicate a list of scalars, preserving author order (used for commit titles, where the
     * chronological order carries meaning).
     *
     * @param  array<int,mixed>  $values
     * @return string[]
     */
    private function orderedList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            if (is_scalar($value)) {
                $string = (string) $value;
                if ($string !== '' && !in_array($string, $list, true)) {
                    $list[] = $string;
                }
            }
        }

        return $list;
    }

    /**
     * Roadmap refs from a watchlist observation (`roadmap: [...]`, or T3's `roadmapRefs: [...]`)
     * or, for the normal feed, the single `relevance` string. Stable refs survive renumbering (§5.6).
     *
     * @param  array<string,mixed>  $obs
     * @return string[]
     */
    private function roadmapRefs(array $obs): array
    {
        foreach (['roadmap', 'roadmapRefs'] as $key) {
            $refs = $obs[$key] ?? null;
            if (is_array($refs)) {
                $list = $this->stringList($refs);
                if ($list !== []) {
                    return $list;
                }
            }
        }

        $relevance = $obs['relevance'] ?? null;

        return is_string($relevance) && $relevance !== '' ? [$relevance] : [];
    }

    /**
     * @param  array<int,mixed>  $values
     * @return string[]
     */
    private function stringList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            if (is_scalar($value)) {
                $string = (string) $value;
                if ($string !== '') {
                    $list[] = $string;
                }
            }
        }
        $list = array_values(array_unique($list));
        sort($list, SORT_STRING);

        return $list;
    }
}
