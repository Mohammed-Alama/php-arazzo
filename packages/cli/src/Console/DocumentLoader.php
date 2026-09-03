<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Document\Parser\Exceptions\ParserException;
use Alama\Arazzo\Document\Parser\Loader;
use Alama\Arazzo\Document\Parser\Parser;

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
