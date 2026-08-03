<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolution\Parsers;

use Alama\Arazzo\Loader\NativeJsonDecoder;
use Alama\Arazzo\Loader\SymfonyYamlDecoder;
use Alama\Arazzo\Resolution\AsyncApiResolvedSource;
use Alama\Arazzo\Resolution\Exceptions\SourceParseException;
use Alama\Arazzo\Resolution\ResolvedSource;
use Alama\Arazzo\Resolution\SourceParser;
use Throwable;

final class AsyncApiSourceParser implements SourceParser
{
    public function parse(string $content): ResolvedSource
    {
        try {
            $isYaml = !str_starts_with(trim($content), '{');
            $decoded = $isYaml
                ? (new SymfonyYamlDecoder())->decode($content)
                : (new NativeJsonDecoder())->decode($content);

            if (!is_array($decoded)) {
                throw new SourceParseException('AsyncAPI document root must be an object');
            }

            /** @var array<string, mixed> $decoded */
            return new AsyncApiResolvedSource($decoded);
        } catch (SourceParseException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SourceParseException('Failed to parse AsyncAPI document: ' . $e->getMessage(), 0, $e);
        }
    }
}
