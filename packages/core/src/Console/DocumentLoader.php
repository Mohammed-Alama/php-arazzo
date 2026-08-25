<?php

declare(strict_types=1);

namespace Alama\Arazzo\Console;

use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Parser\Exceptions\ParserException;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Spec\ArazzoDocument;

/** Shared document loading for console commands. */
final class DocumentLoader
{
    /**
     * @throws LoaderException
     * @throws ParserException
     */
    public static function load(string $file): ArazzoDocument
    {
        $loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());

        return (new Parser())->parse($loader->load($file));
    }
}
