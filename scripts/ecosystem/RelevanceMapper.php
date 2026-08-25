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
        'spec' => 'Conformance / schema validation',
        'schema' => 'P1-7 JSON Schema layer',
        'breaking' => 'Potential breaking change (2.0)',
    ];

    /**
     * @param string[] $tags
     */
    public static function map(array $tags): ?string
    {
        foreach ($tags as $t) {
            $k = strtolower($t);
            if (isset(self::MAP[$k])) {
                return self::MAP[$k];
            }
        }

        return null;
    }
}
