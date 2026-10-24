<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\JsonPointer;

/**
 * RFC 6901 §5 example document. Runtime expressions carry the JSON-string
 * pointer form, so `%` and the other punctuation characters are literal key
 * characters: the §6 URI-fragment form (`#/c%25d`) is not decoded.
 */
const RFC6901_DOCUMENT = [
    'foo' => ['bar', 'baz'],
    '' => 0,
    'a/b' => 1,
    'c%d' => 2,
    'e^f' => 3,
    'g|h' => 4,
    'i\\j' => 5,
    'k"l' => 6,
    ' ' => 7,
    'm~n' => 8,
];

dataset('rfc6901_examples', [
    'whole document' => ['', RFC6901_DOCUMENT],
    '/foo' => ['/foo', ['bar', 'baz']],
    '/foo/0' => ['/foo/0', 'bar'],
    'empty key' => ['/', 0],
    'escaped slash' => ['/a~1b', 1],
    'literal percent' => ['/c%d', 2],
    'literal caret' => ['/e^f', 3],
    'literal pipe' => ['/g|h', 4],
    'literal backslash' => ['/i\\j', 5],
    'literal quote' => ['/k"l', 6],
    'literal space' => ['/ ', 7],
    'escaped tilde' => ['/m~0n', 8],
]);

it('resolves every RFC 6901 §5 example', function (string $pointer, mixed $expected): void {
    expect(JsonPointer::resolve(RFC6901_DOCUMENT, $pointer))->toBe($expected);
})->with('rfc6901_examples');

it('strips exactly one leading slash', function (): void {
    $document = ['' => ['a' => 'nested'], 'a' => 'top'];

    expect(JsonPointer::resolve($document, '//a'))->toBe('nested')
        ->and(JsonPointer::resolve($document, '/a'))->toBe('top');
});

it('rejects a non-empty pointer without a leading slash', function (): void {
    expect(JsonPointer::resolve(RFC6901_DOCUMENT, 'a/b'))->toBeNull()
        ->and(JsonPointer::resolve(RFC6901_DOCUMENT, 'foo'))->toBeNull()
        ->and(JsonPointer::isValid('a/b'))->toBeFalse()
        ->and(JsonPointer::isValid('foo'))->toBeFalse()
        ->and(JsonPointer::isValid('/a/b'))->toBeTrue()
        ->and(JsonPointer::isValid('/'))->toBeTrue()
        ->and(JsonPointer::isValid(''))->toBeTrue();
});

it('unescapes tilde before slash', function (): void {
    // "~01" decodes to "~1"; applying ~0 first would turn it into a slash.
    expect(JsonPointer::resolve(['a~1b' => 'tilde-one'], '/a~01b'))->toBe('tilde-one');
});

it('returns null for missing keys and scalar intermediates', function (): void {
    expect(JsonPointer::resolve(RFC6901_DOCUMENT, '/missing'))->toBeNull()
        ->and(JsonPointer::resolve(RFC6901_DOCUMENT, '/foo/9'))->toBeNull()
        ->and(JsonPointer::resolve(RFC6901_DOCUMENT, '/foo/0/name'))->toBeNull()
        ->and(JsonPointer::resolve(RFC6901_DOCUMENT, '/nope/deeper'))->toBeNull();
});

it('does not read past the end of an array', function (): void {
    // RFC 6901 reserves '-' for JSON Patch "add"; a read never matches an index.
    expect(JsonPointer::resolve(['items' => [1, 2, 3]], '/items/-'))->toBeNull();
});

it('does not percent-decode the URI fragment form', function (): void {
    expect(JsonPointer::resolve(RFC6901_DOCUMENT, '/c%25d'))->toBeNull();
});
