<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser\Decoders;

use Alama\Arazzo\Document\Parser\Exceptions\DecodeException;
use Alama\Arazzo\Document\Parser\Interfaces\YamlDecoder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class SymfonyYamlDecoder implements YamlDecoder
{
    public function decode(string $source): mixed
    {
        try {
            return Yaml::parse($source);
        } catch (ParseException $e) {
            throw new DecodeException($e->getMessage(), 0, $e);
        }
    }
}
