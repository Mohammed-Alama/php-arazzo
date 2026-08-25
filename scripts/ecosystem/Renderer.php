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
        $total = count($feed);
        $bySeverity = ['breaking' => 0, 'actionable' => 0, 'watch' => 0];
        $byRelevance = [];
        $bySource = [];
        foreach ($feed as $row) {
            $sev = $row['severity'] ?? 'watch';
            $bySeverity[$sev] = ($bySeverity[$sev] ?? 0) + 1;
            $rel = $row['relevance'] ?? 'uncategorized';
            $rel = $rel !== '' ? $rel : 'uncategorized';
            $byRelevance[$rel] = ($byRelevance[$rel] ?? 0) + 1;
            $src = $row['source'] ?? 'unknown';
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;
        }
        arsort($byRelevance);
        arsort($bySource);

        $out = "# Ecosystem Feed — Human Dashboard\n\n";
        $out .= "> **Generated:** {$generatedAt} by `php scripts/ecosystem/poll.php` · **Internal · Daily · Repo-local** via `gh`\n";
        $out .= "> **Sources:** 54 github (`30 OAI/*` + `4 usearazzo/*` + `20 runners/validators/generators`) from `config/ecosystem/sources.json` + `config/ecosystem/sources.oai.json` — see `docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md`\n";
        $out .= "> **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php` → `.scratch/ecosystem-triage/<date>.md` (10 tasks, `RelevanceMapper` P0-6/P1-6/P2-1/P2-2)\n\n";
        $out .= "## Summary\n\n";
        $out .= "- **Total events:** {$total} (showing 200 newest)\n";
        $out .= "- **Severity:** breaking **{$bySeverity['breaking']}** · actionable **{$bySeverity['actionable']}** · watch **{$bySeverity['watch']}**\n";
        $out .= '- **Top relevance:** ';
        $topRel = array_slice(array_keys($byRelevance), 0, 5);
        $out .= implode(' · ', array_map(fn ($k) => '`' . str_replace('|', '\\|', $k) . '` (' . $byRelevance[$k] . ')', $topRel)) . "\n";
        $out .= '- **Top sources:** ';
        $topSrc = array_slice(array_keys($bySource), 0, 5);
        $out .= implode(' · ', array_map(fn ($k) => '`' . $k . '` (' . $bySource[$k] . ')', $topSrc)) . "\n";
        $out .= "- **Links:** [Raw JSON](storage/ecosystem-feed/feed.json) · [Generated JSON](docs/generated/ecosystem-feed.json) · [Snapshots](storage/ecosystem-feed/snapshots/) · [Plan](docs/superpowers/plans/2026-08-25-ecosystem-feed-plan.md)\n\n";
        $out .= "## Legend\n\n";
        $out .= "- **Severity:** `breaking` = requires immediate planning (spec 2.0, wsdl, schema) · `actionable` = new release/tag worth reviewing · `watch` = commit/issue for context\n";
        $out .= "- **Relevance:** `P0-6 source routing (wsdl)` · `P1-6/P0-5 xml/xpath` · `P1-7 schema` · `P2-1 CLI` · `P2-2 MCP` (from `scripts/ecosystem/RelevanceMapper.php`)\n";
        $out .= "- **Tags:** `soap,wsdl,xml,xpath,mcp,cli,actor,loop,a2a,grpc,graphql` derived from title/body/labels\n\n";
        $out .= "## Breaking — needs attention\n\n";
        $out .= self::renderGroup($feed, 'breaking');
        $out .= "\n## Actionable — new releases/tags to review\n\n";
        $out .= self::renderGroup($feed, 'actionable');
        $out .= "\n## Watch — context (commits/issues/checksums)\n\n";
        $out .= self::renderGroup($feed, 'watch');
        $out .= "\n## All events — newest 200\n\n";
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
                $rel = str_replace('|', '\\|', $row['relevance'] ?? '');
                $out .= "| {$date} | {$source} | {$type} | {$titleMd} | {$tags} | {$sev} | {$rel} |\n";
            }
        }
        $out .= "\n## How to use\n\n";
        $out .= "- **Human:** read `Summary` → `Breaking` → `Triage` (`php .agents/skills/ecosystem-triage/scripts/analyze.php`)\n";
        $out .= "- **Poll:** `composer ecosystem:poll:dry` (dry) / `composer ecosystem:poll` (commit) — uses `gh` when available, `curl` fallback + `GITHUB_TOKEN`\n";
        $out .= "- **Filter:** `php scripts/ecosystem/poll.php --dry-run --source=strefethen/arazzo-cli --limit=5`\n";
        $out .= "- **Triage:** `php .agents/skills/ecosystem-triage/scripts/analyze.php --since=2026-08-18 --verbose`\n";
        $out .= "- **Snapshots:** `storage/ecosystem-feed/snapshots/YYYY-MM-DD/` (30-day prune) · **Feed:** `storage/ecosystem-feed/feed.json` + `docs/generated/ecosystem-feed.json`\n";

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $feed
     */
    private static function renderGroup(array $feed, string $severity): string
    {
        $filtered = array_values(array_filter($feed, fn ($r) => ($r['severity'] ?? '') === $severity));
        if ($filtered === []) {
            return "_None — no {$severity} events in current window._\n";
        }
        // Group by relevance
        $byRel = [];
        foreach ($filtered as $row) {
            $rel = $row['relevance'] ?? 'uncategorized';
            $rel = $rel !== '' ? $rel : 'uncategorized';
            $byRel[$rel][] = $row;
        }
        // Sort relevance groups by count desc, but P0 first if breaking
        uksort($byRel, function ($a, $b) use ($byRel) {
            $ca = count($byRel[$a]);
            $cb = count($byRel[$b]);
            if ($ca !== $cb) {
                return $cb <=> $ca;
            }

            return strcmp($a, $b);
        });
        $out = '';
        foreach ($byRel as $rel => $rows) {
            $out .= '### ' . str_replace('|', '\\|', $rel) . ' (' . count($rows) . ")\n\n";
            foreach (array_slice($rows, 0, 8) as $row) {
                $date = substr($row['publishedAt'] ?? '', 0, 10);
                $title = $row['title'] ?? '';
                $url = $row['url'] ?? '';
                $src = $row['source'] ?? '';
                $tags = implode(',', $row['tags'] ?? []);
                $titleMd = $url !== '' ? "[{$title}]({$url})" : $title;
                $out .= "- `{$date}` {$titleMd} — `{$src}` · `{$row['type']}` · _" . ($tags !== '' ? $tags : 'no tags') . "_\n";
            }
            if (count($rows) > 8) {
                $out .= '- … and ' . (count($rows) - 8) . " more in this group (see All events table)\n";
            }
            $out .= "\n";
        }

        return $out;
    }
}
