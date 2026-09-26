<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser;

use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Exceptions\DecodeException;
use Alama\Arazzo\Document\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Document\Parser\Interfaces\DecoderInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class Loader
{
    private readonly DecoderRegistry $decoders;

    private function __construct(DecoderRegistry $decoders)
    {
        $this->decoders = $decoders;
    }

    /**
     * Create a Loader with the default decoders (Symfony YAML + PHP native JSON).
     * The decoder implementations are completely hidden from consumers.
     */
    public static function new(): self
    {
        return new self(
            DecoderRegistry::fromArray([
                Format::Yaml->value => new SymfonyYamlDecoder(),
                Format::Json->value => new NativeJsonDecoder(),
            ]),
        );
    }

    /**
     * Create a Loader with custom decoders.
     * Useful for testing or adding new formats without modifying this class.
     *
     * @param  array<string, DecoderInterface>  $decoders  Keys are format values (e.g. 'yaml', 'json')
     */
    public static function withDecoders(array $decoders): self
    {
        return new self(DecoderRegistry::fromArray($decoders));
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

        $decoder = $this->decoders->all()[$format->value] ?? null;
        if ($decoder === null) {
            throw LoaderException::unsupportedFormat($format);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw LoaderException::readFailed($path);
        }

        try {
            /** @phpstan-ignore method.nonObject */
            $data = $decoder->decode($raw);
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
