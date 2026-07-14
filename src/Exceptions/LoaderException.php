<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Exceptions;

final class LoaderException extends ArazzoException
{
    public static function notFound(string $path): self
    {
        return new self("File not found: {$path}", $path, 'loader.not_found');
    }

    public static function notReadable(string $path): self
    {
        return new self("File not readable: {$path}", $path, 'loader.not_readable');
    }

    public static function unsupportedExtension(string $ext): self
    {
        return new self("Unsupported extension '{$ext}' (expected yaml|yml|json)", '', 'loader.unsupported_extension');
    }

    public static function readFailed(string $path): self
    {
        return new self("Failed to read file: {$path}", $path, 'loader.read_failed');
    }

    public static function decodeFailed(string $path, \Throwable $previous): self
    {
        return new self("Failed to decode file: {$path} ({$previous->getMessage()})", $path, 'loader.decode_failed', $previous);
    }

    public static function rootNotObject(string $path): self
    {
        return new self("Root of Arazzo document must be an object: {$path}", $path, 'loader.root_not_object');
    }
}
