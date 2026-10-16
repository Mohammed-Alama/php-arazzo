<?php

declare(strict_types=1);

namespace Ecosystem;

final class RelevanceMapper
{
    /**
     * tag -> stable roadmap ref.
     *
     * Refs use `plan:<file-stem>` / `spec:<file-stem>` / `upstream:<ref>` keys that resolve to the
     * current superpowers plan/spec docs (§5.6), replacing the deleted
     * `docs/arazzo-conformance-and-enhancement-roadmap.md` `Pn-m` scheme (F7). The protocol topics
     * (soap/wsdl/protobuf/graphql) map to Phase F until `topics.json` lands (P0.6).
     *
     * @var array<string,string>
     */
    private const MAP = [
        'soap' => 'plan:phase-f-protocol-packages',
        'wsdl' => 'plan:phase-f-protocol-packages',
        'protobuf' => 'plan:phase-f-protocol-packages',
        'graphql' => 'plan:phase-f-protocol-packages',
        'grpc' => 'plan:phase-f-protocol-packages',
        'a2a' => 'plan:phase-f-protocol-packages',
        'mcp' => 'plan:phase-f-protocol-packages',
        'xml' => 'spec:plugin-stack-oms-multiprotocol',
        'xpath' => 'spec:plugin-stack-oms-multiprotocol',
        'transformer' => 'plan:phase-e-runner-oms',
        'function' => 'plan:phase-e-runner-oms',
        'runner' => 'plan:phase-e-runner-oms',
        'actor' => 'upstream:OAI/Arazzo-Specification#410',
        'human' => 'upstream:OAI/Arazzo-Specification#410',
        'loop' => 'upstream:OAI/Arazzo-Specification#410',
        'schema' => 'plan:php-arazzo-spec-suite-plan-index',
        'cli' => 'plan:php-arazzo-spec-suite-plan-index',
        'spec' => 'plan:php-arazzo-spec-suite-plan-index',
        'moonwalk' => 'plan:php-arazzo-spec-suite-plan-index',
        'security' => 'plan:php-arazzo-spec-suite-plan-index',
        'depbump' => 'dependency maintenance (no roadmap)',
    ];

    /**
     * @param  string[]  $tags
     *
     * Iterates tags in an explicit priority order instead of insertion order so that
     * high-specificity roadmap features always win over generic catch-alls.
     * e.g. a dep-bump PR tagged [soap, depbump] -> protocol packages (correct); one tagged only
     * [depbump] -> "dependency maintenance" (correct fallback).
     */
    public static function map(array $tags): ?string
    {
        // Explicit priority: most specific roadmap features first, catch-alls last.
        $priority = [
            'soap', 'wsdl', 'protobuf', 'graphql', 'grpc', 'a2a', // Phase F protocol packages
            'xpath', 'xml',                                       // Phase F spec
            'schema',                                             // JSON Schema layer (spec-suite)
            'mcp',                                                // Phase F
            'cli',                                                // CLI binary
            'transformer', 'function', 'runner',                  // Runner
            'actor', 'human', 'loop',                             // Issue #410 kind discriminator
            'moonwalk',                                           // OAI next-gen spec
            'security',                                           // OAI sig-security
            'spec',                                               // Conformance (broad catch-all)
            'depbump',                                            // Dependency maintenance (lowest)
        ];

        $tagSet = array_flip(array_map('strtolower', $tags));

        foreach ($priority as $t) {
            if (isset($tagSet[$t], self::MAP[$t])) {
                return self::MAP[$t];
            }
        }

        return null;
    }
}
