<?php

declare(strict_types=1);

namespace Ecosystem;

require_once __DIR__.'/TopicMatcher.php';

/**
 * Classifies a normalised observation as **Act / Watch / Noise** (§5.6, fixes F5–F9).
 *
 * `score = sourceWeight × kindWeight × topicWeight × transitionWeight (+ watchlist bonus)`.
 * The tier is rule-based (the explicit §5.6 tier rules); the score is the ordering signal
 * inside a tier. `breaking` is **derived** on Act items only — it is never a keyword tag.
 */
final class Scorer
{
    public const ACT = 'act';

    public const WATCH = 'watch';

    public const NOISE = 'noise';

    public const WATCHLIST_BONUS = 25;

    public function __construct(private readonly TopicMatcher $topics) {}

    public static function fromDefault(): self
    {
        return new self(TopicMatcher::fromDefault());
    }

    /**
     * @param  array<string,mixed>  $observation  source/type/title/body/author/labels/state/merged/base/prerelease
     * @return array{tier:string,score:int,why:string[],topics:string[],roadmapRefs:string[],breaking:bool}
     */
    public function score(array $observation, bool $watchlist = false): array
    {
        $source = (string) ($observation['source'] ?? '');
        $type = (string) ($observation['type'] ?? $observation['kind'] ?? 'unknown');
        $kind = (string) ($observation['kind'] ?? $type);
        $title = (string) ($observation['title'] ?? '');
        $author = (string) ($observation['author'] ?? '');
        $state = strtolower((string) ($observation['state'] ?? ''));
        $merged = ($observation['merged'] ?? false) === true || $state === 'merged';
        $prerelease = ($observation['prerelease'] ?? false) === true;
        $base = (string) ($observation['base'] ?? '');
        $version = (string) ($observation['version'] ?? $this->versionOf((string) ($observation['externalId'] ?? '')));

        $topics = $this->stringList($observation['topics'] ?? $observation['tags'] ?? []);
        if ($topics === [] && !isset($observation['topics'])) {
            $topics = $this->topics->topics($observation);
        }
        $roadmapRefs = isset($observation['roadmapRefs']) && is_array($observation['roadmapRefs'])
            ? $this->stringList($observation['roadmapRefs'])
            : $this->topics->roadmapFor($topics);

        $isSpecSource = $source === 'OAI/Arazzo-Specification';
        $isSpecChange = in_array($type, ['spec', 'spec_text', 'spec-text', 'schema'], true)
            || in_array($kind, ['spec.text_changed', 'schema.changed'], true);
        $isSpecRelease = $isSpecSource && $type === 'release' && !$prerelease;
        $isTrackedRelease = $type === 'release' && !$prerelease
            && $this->topics->isTrackedLibrary($source)
            && $this->isMajorOrMinor($version);
        $isDevMerge = $type === 'pr' && $merged && preg_match('/(?:^|[^a-z])dev\b/i', $base) === 1;
        $isMeeting = $type === 'issue' && preg_match('/^\s*meeting\b/i', $title) === 1;
        $hasTransition = $isSpecChange || $merged || $state === 'ready'
            || in_array($kind, ['pr.state_changed', 'pr.retargeted', 'pr.labels_changed', 'issue.state_changed'], true);

        // ---- Noise (§5.6): counted, never listed.
        $noise = [];
        if ($this->topics->isBot($author)) {
            $noise[] = 'bot-author';
        }
        if ($this->topics->isNoiseSource($source)) {
            $noise[] = 'noise-source';
        }
        if ($this->topics->isNoiseTitle($title)) {
            $noise[] = 'noise-title';
        }
        if ($type === 'tag' && $this->isPrerelease($version)) {
            $noise[] = 'prerelease-tag';
        }
        if ($type === 'release' && $prerelease) {
            $noise[] = 'prerelease-release';
        }

        if ($noise !== []) {
            return [
                'tier' => self::NOISE,
                'score' => 0,
                'why' => $noise,
                'topics' => $topics,
                'roadmapRefs' => [],
                'breaking' => false,
            ];
        }

        // ---- Act (§5.6): watchlist transition, spec/schema change, spec-repo release,
        // tracked-library major/minor release, PR merged into a spec dev branch.
        $why = [];
        if ($watchlist) {
            $why[] = 'watchlist';
        }
        if ($isSpecSource) {
            $why[] = 'spec-repo';
        }
        if ($isSpecChange) {
            $why[] = $type === 'schema' || $kind === 'schema.changed' ? 'schema-changed' : 'spec-text-changed';
        }
        if ($isSpecRelease) {
            $why[] = 'spec-repo-release';
        }
        if ($isTrackedRelease) {
            $why[] = 'tracked-library-release';
        }
        if ($isDevMerge) {
            $why[] = 'merged-to-dev-branch';
        }
        if ($watchlist && $hasTransition) {
            $why[] = 'watchlist-transition';
        }

        $act = $isSpecChange || $isSpecRelease || $isTrackedRelease || $isDevMerge
            || ($watchlist && $hasTransition);

        $tier = $act ? self::ACT : self::WATCH;
        if (!$act) {
            if ($isMeeting) {
                $why[] = 'meeting-agenda';
            }
            if ($topics !== []) {
                $why[] = 'topic-matched';
            }
            if (!$isSpecSource && !$isMeeting && $topics === []) {
                $why[] = 'peer-event';
            }
        }

        $score = $this->topics->sourceWeight($source)
            * $this->kindWeight($type, $state, $merged, $prerelease, $isSpecChange, $isSpecRelease)
            * $this->topics->topicWeight($topics)
            * $this->transitionWeight($type, $state, $merged, $prerelease, $isSpecChange);
        if ($watchlist) {
            $score += self::WATCHLIST_BONUS;
        }

        return [
            'tier' => $tier,
            'score' => $score,
            'why' => $why,
            'topics' => $topics,
            'roadmapRefs' => $roadmapRefs,
            'breaking' => $this->breaking($observation, $tier, $type, $kind, $version, $isSpecChange, $isSpecRelease, $isTrackedRelease),
        ];
    }

    // ---------------------------------------------------------------- internals

    /** `breaking` is derived on Act items only (§5.6). */
    private function breaking(array $observation, string $tier, string $type, string $kind, string $version, bool $isSpecChange, bool $isSpecRelease, bool $isTrackedRelease): bool
    {
        if ($tier !== self::ACT) {
            return false;
        }

        if ($isSpecRelease) {
            return true; // a spec release always carries the schema + spec text
        }
        if ($type === 'schema' || $kind === 'schema.changed') {
            return true;
        }
        if ($isSpecChange && $this->mentionsSchema($observation)) {
            return true;
        }
        if ($isTrackedRelease && $this->isMajor($version)) {
            return true;
        }

        return $this->hasBreakingLabel($observation);
    }

    private function kindWeight(string $type, string $state, bool $merged, bool $prerelease, bool $isSpecChange, bool $isSpecRelease): int
    {
        if ($isSpecChange || $isSpecRelease) {
            return 5;
        }

        return match (true) {
            $type === 'release' => $prerelease ? 1 : 4,
            $type === 'tag' => 1,
            $type === 'pr' && $merged => 4,
            $type === 'pr' && $state === 'ready' => 3,
            $type === 'pr' => 2,
            $type === 'issue' => 2,
            default => 1,
        };
    }

    private function transitionWeight(string $type, string $state, bool $merged, bool $prerelease, bool $isSpecChange): int
    {
        if ($isSpecChange) {
            return 3;
        }
        if ($merged) {
            return 3;
        }
        if ($state === 'ready') {
            return 2;
        }
        if ($type === 'release' && !$prerelease) {
            return 2;
        }

        return 1;
    }

    private function mentionsSchema(array $observation): bool
    {
        foreach ((array) ($observation['files'] ?? []) as $file) {
            if (is_string($file) && stripos($file, 'schema') !== false) {
                return true;
            }
        }
        $text = (string) ($observation['title'] ?? '').' '.(string) ($observation['body'] ?? '');

        return preg_match('/\bjson[ -]?schema\b|\bschema\b/i', $text) === 1;
    }

    private function hasBreakingLabel(array $observation): bool
    {
        $labels = (array) ($observation['labels'] ?? []);
        foreach ($labels as $label) {
            if (is_string($label) && preg_match('/\b(?:breaking|deprecat)/i', $label) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isMajorOrMinor(string $version): bool
    {
        return preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', $version, $m) === 1 && $m[3] === '0';
    }

    private function isMajor(string $version): bool
    {
        return preg_match('/^v?(\d+)\.0\.0/', $version) === 1;
    }

    private function isPrerelease(string $version): bool
    {
        return str_contains($version, '-');
    }

    private function versionOf(string $externalId): string
    {
        $version = $externalId;
        foreach (['release:', 'tag:', 'npm:'] as $prefix) {
            if (str_starts_with($version, $prefix)) {
                $version = substr($version, strlen($prefix));
            }
        }
        // Monorepo releases carry the package name: `@redocly/cli@2.63.0`.
        if (str_contains($version, '@')) {
            $version = substr($version, (int) strrpos($version, '@') + 1);
        }

        return $version;
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

        return array_values(array_unique($list));
    }
}
