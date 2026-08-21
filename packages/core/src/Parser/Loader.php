<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser;

use Alama\Arazzo\Parser\Decoders\JsonDecoder;
use Alama\Arazzo\Parser\Decoders\YamlDecoder;
use Alama\Arazzo\Parser\Exceptions\DecodeException;
use Alama\Arazzo\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Spec\Enum\Format;
use Alama\Arazzo\Spec\RawDocument;

final class Loader
{
    public function __construct(
        private readonly YamlDecoder $yaml,
        private readonly JsonDecoder $json,
    ) {
    }

    public function load(string $path): RawDocument
    {
        if (!is_file($path)) {
            throw LoaderException::notFound($path);
        }
        if (!is_readable($path)) {
            throw LoaderException::notReadable($path);
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $format = Format::fromExtension($ext)
            ?? throw LoaderException::unsupportedExtension($ext);

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw LoaderException::readFailed($path);
        }

        try {
            $data = $format === Format::Yaml
                ? $this->yaml->decode($raw)
                : $this->json->decode($raw);
        } catch (DecodeException $e) {
            throw LoaderException::decodeFailed($path, $e);
        }

        if (!is_array($data) || (array_is_list($data) && $data !== [])) {
            throw LoaderException::rootNotObject($path);
        }

        /** @var array<string,mixed> $data */
        return new RawDocument($data, $path, $format);
    }
}
