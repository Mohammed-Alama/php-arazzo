<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution\Parsers;

use Alama\LaravelArazzo\Resolution\AsyncApiResolvedSource;
use Alama\LaravelArazzo\Resolution\Exceptions\SourceParseException;
use Alama\LaravelArazzo\Resolution\Parsers\AsyncApiSourceParser;

it('parses a JSON AsyncAPI document', function (): void {
    $parser = new AsyncApiSourceParser();

    $resolved = $parser->parse(json_encode(['asyncapi' => '2.6.0', 'channels' => []]));

    expect($resolved)->toBeInstanceOf(AsyncApiResolvedSource::class);
    expect($resolved->extract('/asyncapi'))->toBe('2.6.0');
});

it('parses a YAML AsyncAPI document', function (): void {
    $parser = new AsyncApiSourceParser();

    $resolved = $parser->parse("asyncapi: 2.6.0\nchannels: {}\n");

    expect($resolved->extract('/asyncapi'))->toBe('2.6.0');
});

it('throws SourceParseException for invalid content', function (): void {
    $parser = new AsyncApiSourceParser();

    expect(fn () => $parser->parse("not: [valid\n"))->toThrow(SourceParseException::class);
});
