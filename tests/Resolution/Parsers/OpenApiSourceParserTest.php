<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution\Parsers;

use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;
use Alama\LaravelArazzo\Resolution\OpenApiResolvedSource;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;

it('parses openapi json and extracts a nested value', function (): void {
    $parser = new OpenApiSourceParser();
    $json = '{"openapi":"3.0.0","info":{"title":"Test API","version":"1.0.0"},"paths":{}}';

    $resolved = $parser->parse($json);

    expect($resolved)->toBeInstanceOf(OpenApiResolvedSource::class);
    expect($resolved->extract('/info/title'))->toBe('Test API');
});

it('parses openapi yaml and extracts a nested value', function (): void {
    $parser = new OpenApiSourceParser();
    $yaml = "openapi: \"3.0.0\"\ninfo:\n  title: YAML API\n  version: \"2.0\"\npaths: {}\n";

    $resolved = $parser->parse($yaml);

    expect($resolved->extract('/info/title'))->toBe('YAML API');
});

it('returns the whole document on empty json pointer', function (): void {
    $parser = new OpenApiSourceParser();
    $json = '{"openapi":"3.0.0","info":{"title":"Whole Doc","version":"1.0.0"},"paths":{}}';

    $resolved = $parser->parse($json);
    $whole = $resolved->extract('');

    expect($whole)->toBeObject();
});

it('throws UnresolvableReferenceException on missing path', function (): void {
    $parser = new OpenApiSourceParser();
    $json = '{"openapi":"3.0.0","info":{"title":"Test API","version":"1.0.0"},"paths":{}}';

    $resolved = $parser->parse($json);
    $resolved->extract('/info/version/missing');
})->throws(UnresolvableReferenceException::class);

it('throws SourceParseException on bad json', function (): void {
    $parser = new OpenApiSourceParser();
    $parser->parse('not valid json at all {{ broken');
})->throws(SourceParseException::class);
