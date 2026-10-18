<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Engine\Execution;

use Alama\Arazzo\Engine\Execution\SchemaValidator;
use cebe\openapi\spec\Schema;

it('validates a schema that declares no composition keywords', function (): void {
    // `Schema::attributeDefaults()` hands back null for allOf/anyOf/oneOf and
    // `SpecBaseObject::__get()` returns that default, so the null tolerance in
    // the validator is load-bearing even though PHPStan reads the @property
    // docblock as non-nullable.
    $schema = new Schema(['type' => 'string']);

    expect($schema->allOf)->toBeNull()
        ->and(SchemaValidator::validate($schema, 'ok'))->toBe([]);
});

it('validates every branch of an anyOf composition', function (): void {
    $schema = new Schema([
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'integer'],
        ],
    ]);

    expect(SchemaValidator::validate($schema, 'text'))->toBe([])
        ->and(SchemaValidator::validate($schema, 7))->toBe([])
        ->and(SchemaValidator::validate($schema, true))->toBe([
            ['path' => '/', 'message' => 'value does not match any of the required schemas (anyOf)'],
        ]);
});

it('validates the constraints of an allOf composition', function (): void {
    $schema = new Schema([
        'allOf' => [
            ['type' => 'string', 'minLength' => 3],
        ],
    ]);

    expect(SchemaValidator::validate($schema, 'abc'))->toBe([])
        ->and(SchemaValidator::validate($schema, 'ab'))->toBe([
            ['path' => '/', 'message' => 'expected at least 3 characters, got 2'],
        ]);
});

it('validates a numerically named property as an object member', function (): void {
    // `{"1": "x"}` decodes to the int key 1, so a member map is array-keyed
    // rather than string-keyed; filtering it would skip real properties.
    $schema = new Schema([
        'type' => 'object',
        'properties' => ['1' => ['type' => 'integer']],
    ]);

    expect(SchemaValidator::validate($schema, [1 => 'x']))->toBe([
        ['path' => '/1', 'message' => "expected type 'integer', got string"],
    ]);
});
