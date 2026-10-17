<?php

declare(strict_types=1);

namespace Ecosystem;

require_once __DIR__.'/TopicMatcher.php';

/**
 * Maps matched topics (or legacy tags) to a stable roadmap ref (§5.6, fixes F7).
 *
 * Refs use the `plan:<file-stem>` / `spec:<file-stem>` / `upstream:<owner>/<repo>#<n>` key space
 * that resolves to the current superpowers plan/spec docs (A8) — the dead
 * `docs/arazzo-conformance-and-enhancement-roadmap.md` `Pn-m` scheme is gone. The topic → ref
 * mapping is authored in `config/ecosystem/topics.json`; this class only exposes it, so there is
 * a single source of truth. {@see self::refs()} returns every ref that can appear in
 * `roadmapRefs`, which is what the A8 check and its test consume.
 */
final class RelevanceMapper
{
    /**
     * Legacy tags that are not topics but still carry a roadmap ref.
     *
     * @var array<string,string>
     */
    private const FALLBACK = [
        'spec' => 'plan:php-arazzo-spec-suite-plan-index',
    ];

    /** @var array<string,string>|null */
    private static ?array $map = null;

    /** @var string[] */
    private static array $order = [];

    /** @var string[]|null */
    private static ?array $allRefs = null;

    /**
     * Primary ref for the highest-priority matched tag/topic (declaration order), or null.
     *
     * @param  string[]  $tags
     */
    public static function map(array $tags): ?string
    {
        self::load();
        $byTag = array_flip(array_map('strtolower', $tags));

        foreach (self::$order as $topic) {
            if (isset($byTag[strtolower($topic)]) && isset(self::$map[$topic])) {
                return self::$map[$topic];
            }
        }
        foreach (self::FALLBACK as $tag => $ref) {
            if (isset($byTag[$tag])) {
                return $ref;
            }
        }

        return null;
    }

    /**
     * Every ref this mapper (and `topics.json`) can emit — the A8 universe.
     *
     * @return string[]
     */
    public static function refs(): array
    {
        self::load();

        return self::$allRefs ?? [];
    }

    public static function topicsPath(): string
    {
        return TopicMatcher::defaultPath();
    }

    private static function load(): void
    {
        if (self::$map !== null) {
            return;
        }

        self::$map = self::FALLBACK;
        $refs = array_flip(array_values(self::FALLBACK));
        $path = self::topicsPath();
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            $topics = is_array($data) ? ($data['topics'] ?? []) : [];
            if (is_array($topics)) {
                $order = [];
                foreach ($topics as $name => $spec) {
                    if (!is_array($spec)) {
                        continue;
                    }
                    $order[] = (string) $name;
                    foreach ((array) ($spec['roadmap'] ?? []) as $ref) {
                        if (is_string($ref) && $ref !== '') {
                            $refs[$ref] = true;
                        }
                    }
                    $first = self::firstString((array) ($spec['roadmap'] ?? []));
                    if ($first !== null) {
                        self::$map[(string) $name] = $first;
                    }
                }
                self::$order = $order;
            }
        }

        self::$allRefs = array_keys($refs);
    }

    /**
     * @param  array<int,mixed>  $values
     */
    private static function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
