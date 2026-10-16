<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Matches raw observations against the authored topic vocabulary in
 * `config/ecosystem/topics.json` (§5.6, fixes F5–F7).
 *
 * Matching rules:
 *  - haystack = title + labels (+ the first ~500 chars of body **only** for non-bot authors);
 *  - the URL is never part of the haystack (F5: every GitHub URL carries the repo slug);
 *  - patterns are word-boundary regexes, case-insensitive unless the topic sets
 *    `"caseSensitive": true` (so `A2A` cannot match a commit SHA);
 *  - a `scope` restricts a topic's text match to matching sources (glob);
 *  - a `sources` list auto-matches by source regardless of text (bare version tags).
 */
final class TopicMatcher
{
    /** Bodies beyond this length are not scanned (token/recall guard, §5.6). */
    public const BODY_LIMIT = 500;

    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Missing topics config at {$path}");
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !isset($data['topics']) || !is_array($data['topics'])) {
            throw new \RuntimeException("Invalid topics config at {$path}: expected a `topics` object");
        }

        return new self($data);
    }

    public static function defaultPath(): string
    {
        return dirname(__DIR__, 2).'/config/ecosystem/topics.json';
    }

    public static function fromDefault(): self
    {
        return self::fromFile(self::defaultPath());
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        return $this->config;
    }

    /** @return array<string,array<string,mixed>> */
    public function topicsConfig(): array
    {
        $topics = $this->config['topics'] ?? [];

        return is_array($topics) ? $topics : [];
    }

    /**
     * Topic names an observation matches, in declaration order.
     *
     * @param  array<string,mixed>  $raw  source/title/labels/body/author (URL is ignored)
     * @return string[]
     */
    public function topics(array $raw): array
    {
        $source = (string) ($raw['source'] ?? '');
        $title = (string) ($raw['title'] ?? '');
        $body = (string) ($raw['body'] ?? '');
        $author = (string) ($raw['author'] ?? '');
        $labels = $this->stringList(is_array($raw['labels'] ?? null) ? $raw['labels'] : []);

        // Never scan bot/dep-bump bodies: they embed upstream release notes and commit SHAs (F5).
        $scanBody = !$this->isBot($author) && !$this->isDepBumpTitle($title);

        $haystack = trim($title.' '.implode(' ', $labels));
        if ($scanBody && $body !== '') {
            $haystack .= ' '.substr($body, 0, self::BODY_LIMIT);
        }
        $haystack = trim($haystack);

        $matched = [];
        foreach ($this->topicsConfig() as $name => $spec) {
            if (is_array($spec) && $this->matchesTopic($spec, $haystack, $source)) {
                $matched[] = (string) $name;
            }
        }

        return $matched;
    }

    /**
     * Weight of the highest-weighted topic in a list (1 when unmatched).
     *
     * @param  string[]  $topics
     */
    public function topicWeight(array $topics): int
    {
        $weight = 1;
        foreach ($topics as $topic) {
            $weight = max($weight, $this->weightOf($topic));
        }

        return $weight;
    }

    /**
     * Roadmap refs for a list of topics, unioned in declaration order.
     *
     * @param  string[]  $topics
     * @return string[]
     */
    public function roadmapFor(array $topics): array
    {
        $refs = [];
        foreach ($this->topicsConfig() as $name => $spec) {
            if (!in_array((string) $name, $topics, true) || !is_array($spec)) {
                continue;
            }
            foreach ((array) ($spec['roadmap'] ?? []) as $ref) {
                if (is_string($ref) && $ref !== '') {
                    $refs[$ref] = true;
                }
            }
        }

        return array_keys($refs);
    }

    /** Source weight from the `sources` map (glob); 1 when unmatched. */
    public function sourceWeight(string $source): int
    {
        $weight = 1;
        $map = $this->config['sources'] ?? [];
        if (is_array($map)) {
            foreach ($map as $glob => $value) {
                if (is_string($glob) && is_int($value) && $this->globMatch($glob, $source)) {
                    $weight = max($weight, $value);
                }
            }
        }

        return $weight;
    }

    /** @return string[] */
    public function trackedLibraries(): array
    {
        $libs = $this->config['trackedLibraries'] ?? [];

        return is_array($libs) ? $this->stringList($libs) : [];
    }

    public function isTrackedLibrary(string $source): bool
    {
        foreach ($this->trackedLibraries() as $glob) {
            if ($this->globMatch($glob, $source)) {
                return true;
            }
        }

        return false;
    }

    public function isBot(string $author): bool
    {
        $author = strtolower(trim($author));
        if ($author === '') {
            return false;
        }

        $globs = $this->config['noise']['authors'] ?? [];
        if (is_array($globs)) {
            foreach ($globs as $glob) {
                if (is_string($glob) && $this->globMatch(strtolower($glob), $author)) {
                    return true;
                }
            }
        }

        return str_ends_with($author, '[bot]');
    }

    public function isNoiseSource(string $source): bool
    {
        $sources = $this->config['noise']['sources'] ?? [];
        if (!is_array($sources)) {
            return false;
        }
        foreach ($sources as $glob) {
            if (is_string($glob) && $this->globMatch($glob, $source)) {
                return true;
            }
        }

        return false;
    }

    /** Whether a title matches a noise pattern (bump/deps/sync/docs/typo/CI-only). */
    public function isNoiseTitle(string $title): bool
    {
        $patterns = $this->config['noise']['title'] ?? [];
        if (!is_array($patterns)) {
            return false;
        }
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $this->regexMatches($pattern, $title, false)) {
                return true;
            }
        }

        return false;
    }

    public function isDepBumpTitle(string $title): bool
    {
        return preg_match('~\bbump\b|(?:chore|build)\(deps(?:-dev)?\)~i', $title) === 1;
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string,mixed>  $spec
     */
    private function matchesTopic(array $spec, string $haystack, string $source): bool
    {
        foreach ((array) ($spec['sources'] ?? []) as $glob) {
            if (is_string($glob) && $this->globMatch($glob, $source)) {
                return true;
            }
        }

        $patterns = (array) ($spec['match'] ?? []);
        if ($patterns === []) {
            return false;
        }

        $scope = (array) ($spec['scope'] ?? []);
        if ($scope !== [] && !$this->anyGlobMatches($scope, $source)) {
            return false;
        }

        $caseSensitive = ($spec['caseSensitive'] ?? false) === true;
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $this->regexMatches($pattern, $haystack, $caseSensitive)) {
                return true;
            }
        }

        return false;
    }

    private function weightOf(string $topic): int
    {
        $spec = $this->topicsConfig()[$topic] ?? null;
        if (!is_array($spec)) {
            return 0;
        }

        return is_int($spec['weight'] ?? null) ? $spec['weight'] : 3;
    }

    /** @param array<int,mixed> $globs */
    private function anyGlobMatches(array $globs, string $value): bool
    {
        foreach ($globs as $glob) {
            if (is_string($glob) && $this->globMatch($glob, $value)) {
                return true;
            }
        }

        return false;
    }

    private function globMatch(string $glob, string $value): bool
    {
        if ($glob === '' || $value === '') {
            return false;
        }
        $regex = '#^'.str_replace(['\*', '\?'], ['.*', '.'], preg_quote($glob, '#')).'$#';

        return preg_match($regex, $value) === 1;
    }

    private function regexMatches(string $pattern, string $subject, bool $caseSensitive): bool
    {
        $regex = '~'.$pattern.'~'.($caseSensitive ? '' : 'i');
        $result = @preg_match($regex, $subject);

        return $result === 1;
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
