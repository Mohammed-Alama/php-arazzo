<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Parser;

use Alama\LaravelArazzo\Exceptions\ParserException;
use Alama\LaravelArazzo\Parser\ParseContext;
use Alama\LaravelArazzo\Parser\Parser;

/** Test double exposing protected helpers. */
class ParserProbe extends Parser
{
    /** @param array<string,mixed> $arr */
    public function reqStr(array $arr, string $k, ParseContext $c): string
    {
        return $this->requireString($arr, $k, $c);
    }

    /** @param array<string,mixed> $arr */
    public function optStr(array $arr, string $k, ParseContext $c): ?string
    {
        return $this->optionalString($arr, $k, $c);
    }

    /** @param array<string,mixed> $arr */
    public function reqArr(array $arr, string $k, ParseContext $c): array
    {
        return $this->requireArray($arr, $k, $c);
    }

    /** @param array<string,mixed> $arr */
    public function optArr(array $arr, string $k, ParseContext $c): ?array
    {
        return $this->optionalArray($arr, $k, $c);
    }

    /** @param array<string,mixed> $arr */
    public function optInt(array $arr, string $k, ParseContext $c): ?int
    {
        return $this->optionalInt($arr, $k, $c);
    }

    /** @param array<string,mixed> $arr */
    public function optBool(array $arr, string $k, ParseContext $c): ?bool
    {
        return $this->optionalBool($arr, $k, $c);
    }

    public function reqObj(mixed $n, ParseContext $c): array
    {
        return $this->requireObjectMap($n, $c);
    }

    public function reqList(mixed $n, ParseContext $c): array
    {
        return $this->requireList($n, $c);
    }
}

it('requireString returns value', function (): void {
    $p = new ParserProbe();
    expect($p->reqStr(['a' => 'x'], 'a', new ParseContext('/x')))->toBe('x');
});

it('requireString throws on missing', function (): void {
    (new ParserProbe())->reqStr([], 'a', new ParseContext('/x'));
})->throws(ParserException::class, 'Missing required field');

it('requireString throws on wrong type', function (): void {
    (new ParserProbe())->reqStr(['a' => 1], 'a', new ParseContext('/x'));
})->throws(ParserException::class, 'Expected string');

it('optionalString returns null when absent', function (): void {
    expect((new ParserProbe())->optStr([], 'a', new ParseContext('/x')))->toBeNull();
});

it('requireObjectMap rejects lists', function (): void {
    (new ParserProbe())->reqObj([1, 2, 3], new ParseContext('/x'));
})->throws(ParserException::class, 'Expected object');

it('requireList rejects assoc arrays', function (): void {
    (new ParserProbe())->reqList(['a' => 1], new ParseContext('/x'));
})->throws(ParserException::class, 'Expected list');

it('requireArray returns array', function (): void {
    expect((new ParserProbe())->reqArr(['a' => [1]], 'a', new ParseContext('/x')))->toBe([1]);
});

it('requireArray throws on wrong type', function (): void {
    (new ParserProbe())->reqArr(['a' => 1], 'a', new ParseContext('/x'));
})->throws(ParserException::class, 'Expected array');

it('optionalArray returns null when absent', function (): void {
    expect((new ParserProbe())->optArr([], 'a', new ParseContext('/x')))->toBeNull();
});

it('optionalInt returns value or null', function (): void {
    $p = new ParserProbe();
    expect($p->optInt(['a' => 1], 'a', new ParseContext('/x')))->toBe(1)
        ->and($p->optInt([], 'a', new ParseContext('/x')))->toBeNull();
});

it('optionalBool returns value or null', function (): void {
    $p = new ParserProbe();
    expect($p->optBool(['a' => true], 'a', new ParseContext('/x')))->toBeTrue()
        ->and($p->optBool([], 'a', new ParseContext('/x')))->toBeNull();
});
