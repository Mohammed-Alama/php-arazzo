<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Feature;

use Alama\Arazzo\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Parser\Loader;
use Alama\Arazzo\Parser\Parser;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Validator\RuleSet;
use Alama\Arazzo\Validator\ValidationResult;
use Alama\Arazzo\Validator\Validator;

final class FixtureHarness
{
    /** @return array<string, array{string}> */
    public static function fixtures(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../fixtures/'.$dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.arazzo\.(yaml|json)$/', $file->getFilename())) {
                $files[basename($file->getPathname())] = [$file->getPathname()];
            }
        }

        return $files;
    }

    public static function validate(string $path): ValidationResult
    {
        $doc = (new Parser())->parse(self::load($path));

        return (new Validator(RuleSet::default()))->validate($doc);
    }

    public static function load(string $path): RawDocument
    {
        return (new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder()))->load($path);
    }
}
