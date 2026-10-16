<?php

declare(strict_types=1);

namespace Ecosystem;

final class Normalizer
{
    /**
     * Map raw item from an ingestor to a FeedEvent.
     * Heuristics: title/labels(/human body) -> tags, severity from type/state.
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
        $publishedAt = (string) ($raw['publishedAt'] ?? '');
        $body = (string) ($raw['body'] ?? '');
        $author = (string) ($raw['author'] ?? '');
        $labels = array_map('strval', is_array($raw['labels'] ?? null) ? $raw['labels'] : []);

        // Bot / dependency-bump items carry upstream release notes (and commit SHAs) in their
        // bodies, which produced bogus tags like `breaking` and `a2a` (F5). Never scan bot bodies.
        $authorLc = strtolower($author);
        $isBot = $authorLc !== '' && (str_ends_with($authorLc, '[bot]')
            || in_array($authorLc, ['dependabot', 'renovate', 'github-actions'], true));
        $isDepBump = preg_match('/\bbump\b|\b(?:chore|build)\(deps(?:-dev)?\)/i', $title) === 1;
        $skipBody = $isBot || $isDepBump;

        // Haystack = title + labels (+ body for human items). The URL is never matched: every
        // GitHub URL contains "arazzo"/"openapi" and the repo slug, which falsely tagged every
        // strefethen/arazzo-cli row as `cli` and (nearly) every spec-repo item as `spec` (F5).
        $haystack = $title.' '.implode(' ', $labels).($skipBody ? '' : ' '.$body);
        $lc = strtolower($haystack);

        $tags = [];

        // Word-boundary regexes only (never substrings): `actor` must not match `refactor` or
        // `api-extractor`; `cli` must not match inside a URL/repo slug.
        $keywordMap = [
            'soap' => '/\b(?:soap|wsdl)\b/',
            'wsdl' => '/\bwsdl\b/',
            'xml' => '/\b(?:xml|xpath)\b|\bapplication\/xml\b/',
            'xpath' => '/\bxpath\b/',
            'mcp' => '/\bmcp\b|\bmodel context protocol\b/',
            'cli' => '/\bcli\b|\barazzo[- ]cli\b|\bcommand line\b/',
            'actor' => '/\bactor\b|\bhuman[- ]in[- ]the[- ]loop\b/',
            'human' => '/\bhuman\b/',
            'loop' => '/\b(?:goto|step loop|loop step|retry loop|iteration loop|arazzo loop)\b/',
            'grpc' => '/\bgrpc\b/',
            'graphql' => '/\bgraphql\b/',
            'transformer' => '/\btransformer\b/',
            'function' => '/\bfunction support\b/',
            'runner' => '/\brunner\b|\bstep execution\b|\bretrylimit\b|\bretry limit\b|\bstep response\b|\bsuccess action\b|\bfailure action\b|\bexecution observability\b/',
            'moonwalk' => '/\bmoonwalk\b/',
            'security' => '/\bjose\b|\bjson web\b|\bpii data\b|\bsensitive data\b|\bmessage level security\b|\bsecurity scheme\b/',
            'schema' => '/\bjson[- ]?schema\b|\$schema\b|\bjsonschema\b/',
            'depbump' => '/\bbump\b|\b(?:chore|build)\(deps(?:-dev)?\)/',
        ];

        foreach ($keywordMap as $tag => $pattern) {
            if (preg_match($pattern, $lc) === 1) {
                $tags[] = $tag;
            }
        }

        // `A2A` requires uppercase (or an explicit spelling) so it cannot match a commit SHA.
        if (preg_match('/\bA2A\b/', $haystack) === 1 || preg_match('/\bagent2agent\b|\bagent-to-agent\b/', $lc) === 1) {
            $tags[] = 'a2a';
        }

        // `spec` catch-all (Arazzo/OpenAPI content) on title/labels(+human body) only.
        if (preg_match('/\barazzo\b|\bopenapi\b|\bspecification\b/', $lc) === 1) {
            $tags[] = 'spec';
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

        // Severity: `breaking` is no longer a keyword tag (F5). Only a spec-repo release, or an
        // explicit upstream `breaking` label, marks an event breaking — never Dependabot bodies.
        $labelsLc = strtolower(implode(' ', $labels));
        $explicitBreaking = preg_match('/\bbreaking\b/', $labelsLc) === 1;

        $severity = 'watch';
        if (in_array($type, ['release', 'tag'], true)) {
            $severity = 'actionable';
        }
        if ($type === 'pr' && (($raw['state'] ?? '') === 'merged' || ($raw['merged'] ?? false) === true)) {
            $severity = 'actionable';
        }
        if ($source === 'OAI/Arazzo-Specification' && in_array($type, ['release', 'tag'], true)) {
            $severity = 'breaking'; // spec releases need review
        }
        if ($explicitBreaking) {
            $severity = 'breaking';
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
            if ($ev->publishedAt === '') {
                // No real provider timestamp: never emit an event without one (F2).
                continue;
            }
            if (!isset($seen[$ev->id])) {
                $seen[$ev->id] = true;
                $out[] = $ev;
            }
        }

        return $out;
    }
}
