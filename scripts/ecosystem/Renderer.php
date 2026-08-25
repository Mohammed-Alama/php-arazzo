<?php

declare(strict_types=1);

namespace Ecosystem;

final class Renderer
{
    /**
     * @param array<int,array<string,mixed>> $feed sorted publishedAt desc
     */
    public static function renderMarkdown(array $feed, string $generatedAt): string
    {
        $out = "# Ecosystem Feed\n\n";
        $out .= "> Generated {$generatedAt} by `php scripts/ecosystem/poll.php` · Internal · Daily · Repo-local\n";
        $out .= "> Sources: `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` (30 OAI repos). See `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`.\n\n";
        $out .= "| Date | Source | Type | Title | Tags | Severity | Relevance |\n";
        $out .= "|---|---|---|---|---|---|---|\n";

        if ($feed === []) {
            $out .= "| — | — | — | _no events yet_ | — | — | — |\n";
        } else {
            foreach (array_slice($feed, 0, 200) as $row) {
                $date = substr($row['publishedAt'] ?? '', 0, 10);
                $source = $row['source'] ?? '';
                $type = $row['type'] ?? '';
                $title = str_replace('|', '\\|', $row['title'] ?? '');
                $url = $row['url'] ?? '';
                $titleMd = $url !== '' ? "[{$title}]({$url})" : $title;
                $tags = implode(', ', $row['tags'] ?? []);
                $sev = $row['severity'] ?? '';
                $rel = $row['relevance'] ?? '';
                $out .= "| {$date} | {$source} | {$type} | {$titleMd} | {$tags} | {$sev} | {$rel} |\n";
            }
        }

        $out .= "\n## How to use\n\n";
        $out .= "- Poll: `composer ecosystem:poll` or `php scripts/ecosystem/poll.php --dry-run`\n";
        $out .= "- Commit: `php scripts/ecosystem/poll.php --commit`\n";
        $out .= "- Filter: `php scripts/ecosystem/poll.php --dry-run --source=OAI/Arazzo-Specification --limit=5`\n";
        $out .= "- Snapshots: `storage/ecosystem-feed/snapshots/YYYY-MM-DD/`\n";
        $out .= "- Feed: `storage/ecosystem-feed/feed.json` + `docs/generated/ecosystem-feed.json`\n";

        return $out;
    }
}
