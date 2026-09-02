<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Spec\SourceDocument;
use Alama\Arazzo\Validator\Data\ValidationResult;
use Alama\Arazzo\Validator\RuleSet;
use Alama\Arazzo\Validator\Validator;

/**
 * Runs the vendored OFFICIAL OAI example corpus (see corpus/oai/README.md)
 * through the same conformance stack used by the golden fixtures, so public
 * spec examples double as regression fixtures and feed the published
 * conformance matrix.
 */
final class OaiCorpusRunner
{
    public const DIR = __DIR__.'/corpus/oai';

    /** @return array<string, string> document name => file path */
    public static function documents(): array
    {
        $out = [];

        // Upstream naming is inconsistent: bnpl ships as
        // 'bnpl-arazzo.yaml' (no dot before arazzo), so match both spellings.
        foreach (glob(self::DIR.'/1.0.0/*arazzo.yaml') ?: [] as $file) {
            if (str_ends_with($file, '.openapi.yaml')) {
                continue;
            }

            // 'X.arazzo.yaml' -> 'X'; upstream's 'bnpl-arazzo.yaml' keeps
            // its hyphenated name verbatim.
            $base = (string) basename($file);
            $key = str_ends_with($base, '.arazzo.yaml')
                ? substr($base, 0, -strlen('.arazzo.yaml'))
                : substr($base, 0, -strlen('.yaml'));
            $out[$key] = $file;
        }

        return $out;
    }

    /**
     * Pre-registers locally-vendored OpenAPI companions under the source
     * names used by each example, so resolution never touches the network.
     *
     * @return array<string, SourceDocument>
     */
    public static function localSources(string $documentPath): array
    {
        $decoder = new SymfonyYamlDecoder();
        $decoded = $decoder->decode((string) file_get_contents($documentPath));
        $sources = [];

        foreach ($decoded['sourceDescriptions'] ?? [] as $description) {
            $name = (string) ($description['name'] ?? '');
            $url = (string) ($description['url'] ?? '');

            $local = self::vendoredFileFor($url);

            if ($local !== null && is_file($local)) {
                // canonicalUri must be absolute: cebe refuses to resolve
                // references against a relative base ('./oauth.openapi.yaml').
                $sources[$name] = new SourceDocument(
                    $name,
                    SourceType::Openapi,
                    (string) realpath($local),
                    $decoder->decode((string) file_get_contents($local)),
                );
            }
        }

        return $sources;
    }

    private static function vendoredFileFor(string $url): ?string
    {
        $basename = basename(parse_url($url, PHP_URL_PATH) ?? $url);

        // swagger-petstore ships its document as openapi.yaml; we vendor it
        // under an explicit name to avoid ambiguity.
        $map = [
            'openapi.yaml' => self::DIR.'/remotes/swagger-petstore-openapi.yaml',
        ];

        $candidate = self::DIR.'/1.0.0/'.$basename;

        if (is_file($candidate)) {
            return $candidate;
        }

        return $map[$basename] ?? null;
    }

    /** Tier 1: parse + structural validation against official rules. */
    public static function tier1(string $documentPath): ValidationResult
    {
        $decoder = new SymfonyYamlDecoder();
        $raw = new RawDocument(
            $decoder->decode((string) file_get_contents($documentPath)),
            $documentPath,
            Format::Yaml,
        );

        $document = (new Parser())->parse($raw);

        return (new Validator(RuleSet::default()))->validate($document);
    }

    /** Builds a registry pre-seeded with this document's local companions. */
    public static function registryFor(string $documentPath): SourceRegistry
    {
        $registry = new SourceRegistry(new DefaultSourceResolver([]));

        foreach (self::localSources($documentPath) as $source) {
            $registry->register($source);
        }

        return $registry;
    }
}
