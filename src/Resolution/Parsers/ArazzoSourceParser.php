<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution\Parsers;

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\RawDocument;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Resolution\ArazzoResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Resolution\SourceParser;
use Throwable;

final class ArazzoSourceParser implements SourceParser
{
    public function __construct(private readonly Parser $parser)
    {
    }

    public function parse(string $content): ResolvedSource
    {
        try {
            $isYaml = !str_starts_with(trim($content), '{');
            $decoded = $isYaml
                ? (new SymfonyYamlDecoder())->decode($content)
                : (new NativeJsonDecoder())->decode($content);

            if (!is_array($decoded)) {
                throw new SourceParseException('Arazzo document root must be an object');
            }

            /** @var array<string,mixed> $decoded */
            $raw = new RawDocument($decoded, '', $isYaml ? Format::Yaml : Format::Json);
            $doc = $this->parser->parse($raw);

            return new ArazzoResolvedSource($doc);
        } catch (SourceParseException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SourceParseException('Failed to parse Arazzo document: ' . $e->getMessage(), 0, $e);
        }
    }
}
