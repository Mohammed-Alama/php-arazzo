<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolution\Parsers;

use Alama\Arazzo\Resolution\Exceptions\SourceParseException;
use Alama\Arazzo\Resolution\OpenApiResolvedSource;
use Alama\Arazzo\Resolution\ResolvedSource;
use Alama\Arazzo\Resolution\SourceParser;
use cebe\openapi\Reader;
use Throwable;

final class OpenApiSourceParser implements SourceParser
{
    public function parse(string $content): ResolvedSource
    {
        try {
            $isYaml = !str_starts_with(trim($content), '{');
            $openapi = $isYaml
                ? Reader::readFromYaml($content)
                : Reader::readFromJson($content);

            return new OpenApiResolvedSource($openapi);
        } catch (Throwable $e) {
            throw new SourceParseException('Failed to parse OpenAPI document: ' . $e->getMessage(), 0, $e);
        }
    }
}
