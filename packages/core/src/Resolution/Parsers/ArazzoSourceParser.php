<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolution\Parsers;

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Dto\RawDocument;
use Alama\Arazzo\Loader\NativeJsonDecoder;
use Alama\Arazzo\Loader\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Resolution\ArazzoResolvedSource;
use Alama\Arazzo\Resolution\Exceptions\SourceParseException;
use Alama\Arazzo\Resolution\ResolvedSource;
use Alama\Arazzo\Resolution\SourceParser;
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
