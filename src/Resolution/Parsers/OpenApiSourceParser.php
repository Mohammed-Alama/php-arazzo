<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution\Parsers;

use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\OpenApiResolvedSource;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Resolution\SourceParser;
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
