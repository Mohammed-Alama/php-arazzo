<?php

declare(strict_types=1);

namespace Ecosystem;

final class RelevanceMapper
{
    /** @var array<string,string> tag -> roadmap ref */
    private const MAP = [
        'soap' => 'P0-6 source routing (wsdl type)',
        'wsdl' => 'P0-6 source routing (wsdl type)',
        'xml' => 'P1-6 payload XPath / P0-5 XPath criteria',
        'xpath' => 'P0-5 XPath criteria + P1-6 targetSelectorType',
        'mcp' => 'P2-2 MCP server exposure',
        'cli' => 'P2-1 CLI binary',
        'actor' => 'Issue #410 kind discriminator / human-in-loop',
        'human' => 'Issue #410 kind discriminator / human-in-loop',
        'loop' => 'Issue #410 loops vs goto',
        'a2a' => 'Roadmap A2A step type',
        'grpc' => 'Roadmap gRPC step type',
        'graphql' => 'Roadmap GraphQL step type',
        'transformer' => 'Roadmap transformers/functions',
        'function' => 'Roadmap transformers/functions',
        'runner' => 'Arazzo runner / step execution',
        'moonwalk' => 'OAI Moonwalk (next-gen spec)',
        'security' => 'API security (OAI sig-security)',
        'spec' => 'Conformance / schema validation',
        'schema' => 'P1-7 JSON Schema layer',
        'depbump' => 'Dependency maintenance',
        'breaking' => 'Potential breaking change (2.0)',
    ];

    /**
     * @param  string[]  $tags
     *
     * Iterates tags in an explicit priority order instead of insertion order so that
     * high-specificity roadmap features always win over generic catch-alls.
     * e.g. a dep-bump PR tagged [soap, breaking] → P0-6 (correct); one tagged only
     * [breaking] → "Potential breaking change (2.0)" (correct fallback).
     */
    public static function map(array $tags): ?string
    {
        // Explicit priority: most specific roadmap features first, catch-alls last.
        $priority = [
            'soap', 'wsdl',                                       // P0-6
            'xpath',                                              // P0-5 / P1-6
            'xml',                                                // P1-6
            'schema',                                             // P1-7
            'mcp',                                                // P2-2
            'cli',                                                // P2-1
            'a2a', 'grpc', 'graphql', 'transformer', 'function',  // Roadmap
            'runner',                                             // Arazzo runtime execution
            'actor', 'human',                                     // Issue #410 kind discriminator
            'loop',                                               // Issue #410 loops vs goto
            'moonwalk',                                           // OAI next-gen spec
            'security',                                           // OAI sig-security
            'spec',                                               // Conformance (broad catch-all)
            'depbump',                                            // Dependency maintenance (lowest)
            'breaking',                                           // Fallback: potential breaking change
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
