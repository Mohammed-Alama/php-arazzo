<?php

declare(strict_types=1);

namespace Ecosystem;

final class Normalizer
{
    /**
     * Map raw item from an ingestor to a FeedEvent.
     * Heuristics: label/title/url -> tags, severity from type/state.
     *
     * @param  array<string,mixed>  $raw  must contain at least source/type/externalId/title/url/publishedAt
     */
    public static function normalize(array $raw): FeedEvent
    {
        $source = (string) ($raw['source'] ?? 'unknown');
        $externalId = (string) ($raw['externalId'] ?? $raw['url'] ?? $raw['title'] ?? uniqid('', true));
        $id = FeedEvent::makeId($source, $externalId);
        $type = (string) ($raw['type'] ?? 'unknown');
        $title = (string) ($raw['title'] ?? $externalId);
        $url = (string) ($raw['url'] ?? '');
        $publishedAt = (string) ($raw['publishedAt'] ?? gmdate('c'));

        $haystack = strtolower(implode(' ', [
            $title,
            $raw['body'] ?? '',
            implode(' ', $raw['labels'] ?? []),
            $url,
        ]));
        // Narrower haystack: title + body + labels only (no URL).
        // Used for keywords that would match almost every GitHub URL (e.g. "arazzo", "openapi").
        $titleBodyLabels = strtolower(implode(' ', [
            $title,
            $raw['body'] ?? '',
            implode(' ', $raw['labels'] ?? []),
        ]));

        $tags = [];

        // Keywords that use the full haystack (title+body+labels+URL).
        $keywordMap = [
            'soap' => ['soap', 'wsdl'],
            'wsdl' => ['wsdl'],
            'xml' => ['xml', 'xpath', 'application/xml'],
            'xpath' => ['xpath'],
            'mcp' => ['mcp', 'model context protocol', 'mcp server', 'mcp compiler'],
            'cli' => ['cli ', 'command line', 'respect-cli', 'arazzo-cli'],
            'actor' => ['actor', 'human-in-loop', 'human in the loop', 'human-in-the-loop'],
            'human' => ['human'],
            // Arazzo-specific loop/goto phrasing only — avoids common English words.
            'loop' => ['goto', 'step loop', 'loop step', 'retry loop', 'iteration loop', 'arazzo loop'],
            'a2a' => ['a2a', 'agent2agent', 'agent-to-agent'],
            'grpc' => ['grpc'],
            'graphql' => ['graphql'],
            'transformer' => ['transformer'],
            'function' => ['function support'],
            // 'breaking change' listed first; bare '2.0' dropped (fires on v2.0.x patch strings);
            // 'major' dropped (too broad). Space-bounded ' 2.0 ' + explicit strings catch real spec 2.0.
            'breaking' => ['breaking change', 'breaking', ' 2.0 ', 'arazzo 2.0', 'v2.0.0'],
            // JSON Schema only — bare 'schema' matches too many unrelated contexts.
            'schema' => ['json schema', 'json-schema', '$schema', 'jsonschema'],
            // Arazzo runner / step execution events.
            'runner' => ['runner:', 'step execution', 'retrylimit', 'retry limit', 'step response',
                'success action', 'failure action', 'execution observability', 'arazzo runner'],
            // OAI Moonwalk — next-gen spec design.
            'moonwalk' => ['moonwalk'],
            // API security (JOSE, JWT, sig-security keywords).
            'security' => ['jose ', 'jose,', 'json web', 'pii data', 'sensitive data',
                'message level security', 'security scheme'],
            // Dependency-bump maintenance events — title pattern match.
            'depbump' => ['bump ', 'chore(deps', 'build(deps', 'chore: bump', 'chore(deps-dev'],
        ];

        // Keywords that must NOT fire on the URL (e.g. every GitHub URL contains "arazzo" or "openapi").
        $titleBodyOnlyMap = [
            'spec' => ['arazzo', 'openapi', 'openapi spec', 'specification'],
        ];

        foreach ($keywordMap as $tag => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($haystack, $kw)) {
                    $tags[] = $tag;

                    break;
                }
            }
        }

        foreach ($titleBodyOnlyMap as $tag => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($titleBodyLabels, $kw)) {
                    $tags[] = $tag;

                    break;
                }
            }
        }

        // Source-based tagging: some repos emit bare version tags (e.g. "tag v1.0.2") with no
        // body text, so keyword scanning cannot detect them. The source repo is the only signal.
        /** @var string[] $arazzoimplsources */
        $arazzoimplsources = [
            'frankkilcommins/arazzo2openapi',
            'b-lab-io/pyarazzo',
            'JaredCE/Arazzo-Generator',
            'swaggerexpert/arazzo-criterion',
            'swaggerexpert/arazzo-runtime-expression',
            'jentic/jentic-arazzo-tools',
            'Specmatic/specmatic',
            'OAI/Arazzo-Specification',
            'speclynx/apidom',
            'Redocly/redocly-cli',
            'stoplightio/spectral',
            'speakeasy-api/openapi',
            'jentic/arazzo-engine',
            'workflows-guru/awesome-arazzo',
            'usearazzo/arazzo-toolkit',
        ];

        if (in_array($source, $arazzoimplsources, true) && !in_array('spec', $tags, true)) {
            $tags[] = 'spec';
        }

        if ($source === 'OAI/sig-moonwalk' && !in_array('moonwalk', $tags, true)) {
            $tags[] = 'moonwalk';
        }

        if ($source === 'OAI/sig-security' && !in_array('security', $tags, true)) {
            $tags[] = 'security';
        }

        // Dep-bump events get 'depbump' from the keywordMap above, but the source-based rule
        // may have also added 'spec'. Strip 'spec' so dep-bumps don't land in Conformance.
        if (in_array('depbump', $tags, true)) {
            $tags = array_values(array_diff($tags, ['spec']));
        }

        $tags = array_values(array_unique($tags));

        // severity heuristics
        $severity = 'watch';
        if (in_array($type, ['release', 'tag'], true)) {
            $severity = 'actionable';
        }
        if ($type === 'pr' && (($raw['state'] ?? '') === 'merged' || ($raw['merged'] ?? false) === true)) {
            $severity = 'actionable';
        }
        if (in_array('breaking', $tags, true) || str_contains($haystack, 'breaking change')) {
            $severity = 'breaking';
        }
        if (in_array($source, ['OAI/Arazzo-Specification'], true) && $type === 'release') {
            $severity = 'breaking'; // spec releases need review
        }

        $relevance = RelevanceMapper::map($tags);

        return new FeedEvent($id, $source, $type, $title, $url, $publishedAt, $tags, $severity, $raw, $relevance);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rawItems
     * @return FeedEvent[]
     */
    public static function normalizeMany(array $rawItems): array
    {
        $out = [];
        $seen = [];
        foreach ($rawItems as $raw) {
            $ev = self::normalize($raw);
            if (!isset($seen[$ev->id])) {
                $seen[$ev->id] = true;
                $out[] = $ev;
            }
        }

        return $out;
    }
}
