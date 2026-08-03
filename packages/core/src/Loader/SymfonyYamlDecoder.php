<?php

declare(strict_types=1);

namespace Alama\Arazzo\Loader;

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
