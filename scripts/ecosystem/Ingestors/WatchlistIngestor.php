<?php

declare(strict_types=1);

namespace Ecosystem\Ingestors;

use Ecosystem\GhCli;
use Ecosystem\Watchlist;

require_once dirname(__DIR__).'/GhCli.php';
require_once dirname(__DIR__).'/Watchlist.php';

/**
 * Per-run detail fetch for the tracked watchlist items (§5.5, §5.4).
 *
 * A watchlist item is polled for the fields the status table needs — state, draft/ready,
 * base branch, review/merge status, changed-file list, labels and last activity — not just
 * the list-endpoint summary. {@see observe()} is pure so the mapping can be tested against
 * recorded payloads without network access.
 */
final class WatchlistIngestor
{
    /**
     * Fetch the detail for every watchlist item.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>> normalized observations
     */
    public static function poll(array $items, ?string $token, bool $verbose = false): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['ref'])) {
                continue;
            }

            $parsed = Watchlist::parseRef((string) $item['ref']);
            $repo = $parsed['repo'];
            $number = $parsed['number'];

            // The issues endpoint answers for both issues and PRs; a PR carries `pull_request`.
            $issue = GhCli::api("repos/{$repo}/issues/{$number}", false, $verbose);
            if (!is_array($issue) || $issue === []) {
                if ($verbose) {
                    fwrite(STDERR, "  [watchlist] {$item['ref']}: no payload\n");
                }
                usleep(150000);

                continue;
            }

            if (isset($issue['pull_request'])) {
                $pull = GhCli::api("repos/{$repo}/pulls/{$number}", false, $verbose);
                if (!is_array($pull) || $pull === []) {
                    $pull = $issue;
                }
                $files = GhCli::api("repos/{$repo}/pulls/{$number}/files?per_page=100", false, $verbose);
                $out[] = self::observe($item, $pull, is_array($files) ? $files : [], 'pr');
            } else {
                $out[] = self::observe($item, $issue, [], 'issue');
            }

            usleep(150000);
        }

        return $out;
    }

    /**
     * Pure payload → observation mapping.
     *
     * @param  array<string,mixed>  $item  a `watchlist.json` item
     * @param  array<string,mixed>  $payload  a GitHub PR/issue object
     * @param  array<int,array<string,mixed>>  $files  a `pulls/{n}/files` response
     * @return array<string,mixed>
     */
    public static function observe(array $item, array $payload, array $files = [], string $kind = 'pr'): array
    {
        $ref = (string) ($item['ref'] ?? '');
        $parsed = Watchlist::parseRef($ref);
        $number = (string) ($payload['number'] ?? $parsed['number']);
        $merged = ($payload['merged_at'] ?? null) !== null && ($payload['merged_at'] ?? '') !== '';

        $observed = [
            'source' => $parsed['repo'],
            'type' => $kind,
            'externalId' => ($kind === 'issue' ? 'issue:' : 'pr:').$number,
            'title' => (string) ($payload['title'] ?? $ref),
            'url' => (string) ($payload['html_url'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
            'labels' => self::labels($payload),
            'author' => (string) ($payload['user']['login'] ?? ''),
            'state' => (string) ($payload['state'] ?? ''),
            'comments' => (int) ($payload['comments'] ?? 0),
            'updatedAt' => (string) ($payload['updated_at'] ?? ''),
            'lastActivity' => (string) ($payload['updated_at'] ?? $payload['created_at'] ?? ''),
            'tags' => isset($item['topic']) && $item['topic'] !== '' ? [(string) $item['topic']] : [],
            'roadmap' => is_array($item['roadmap'] ?? null) ? array_values($item['roadmap']) : [],
            'watchlist' => $ref,
            'stance' => (string) ($item['stance'] ?? ''),
        ];

        if ($kind === 'issue') {
            $observed['publishedAt'] = (string) ($payload['updated_at'] ?? $payload['created_at'] ?? '');

            return $observed;
        }

        // A provider timestamp is mandatory (§5.3): merged PRs are dated by the merge.
        $observed['publishedAt'] = $merged
            ? (string) $payload['merged_at']
            : (string) ($payload['updated_at'] ?? $payload['created_at'] ?? '');
        $observed['draft'] = ($payload['draft'] ?? false) === true;
        $observed['merged'] = $merged;
        $observed['base'] = (string) ($payload['base']['ref'] ?? '');
        $observed['mergeable'] = $payload['mergeable'] ?? null;
        $observed['review'] = self::reviewStatus($payload, $merged);
        $observed['files'] = self::changedFiles($files);

        return $observed;
    }

    /** @param array<string,mixed> $payload @return string[] */
    private static function labels(array $payload): array
    {
        $labels = [];
        foreach ($payload['labels'] ?? [] as $label) {
            if (is_array($label) && isset($label['name'])) {
                $labels[] = (string) $label['name'];
            } elseif (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }
        sort($labels, SORT_STRING);

        return array_values(array_unique($labels));
    }

    /** @param array<string,mixed> $payload */
    private static function reviewStatus(array $payload, bool $merged): string
    {
        if ($merged) {
            return 'merged';
        }

        $decision = $payload['reviewDecision'] ?? $payload['review_decision'] ?? null;
        if (!is_string($decision) || $decision === '') {
            return 'none';
        }

        return match (strtoupper($decision)) {
            'APPROVED' => 'approved',
            'CHANGES_REQUESTED' => 'changes-requested',
            'REVIEW_REQUIRED' => 'review-required',
            default => 'none',
        };
    }

    /**
     * @param  array<int,array<string,mixed>>  $files
     * @return string[]
     */
    private static function changedFiles(array $files): array
    {
        $names = [];
        foreach ($files as $file) {
            if (is_array($file) && isset($file['filename']) && is_string($file['filename'])) {
                $names[] = $file['filename'];
            }
        }
        sort($names, SORT_STRING);

        return array_values(array_unique($names));
    }
}
