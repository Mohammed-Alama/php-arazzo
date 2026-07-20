<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Execution\SchemaValidator;
use cebe\openapi\spec\Schema;

it('passes a matching type with no violations', function (): void {
    $schema = new Schema(['type' => 'integer']);

    expect(SchemaValidator::validate($schema, 42))->toBe([]);
});

it('flags a type mismatch', function (): void {
    $schema = new Schema(['type' => 'integer']);

    $violations = SchemaValidator::validate($schema, 'not-an-int');

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/')
        ->and($violations[0]['message'])->toContain('integer');
});

it('accepts number type for both int and float', function (): void {
    $schema = new Schema(['type' => 'number']);

    expect(SchemaValidator::validate($schema, 3))->toBe([]);
    expect(SchemaValidator::validate($schema, 3.14))->toBe([]);
});

it('flags a value outside the enum', function (): void {
    $schema = new Schema(['type' => 'string', 'enum' => ['a', 'b']]);

    expect(SchemaValidator::validate($schema, 'c'))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 'a'))->toBe([]);
});

it('rejects null when not nullable', function (): void {
    $schema = new Schema(['type' => 'string']);

    $violations = SchemaValidator::validate($schema, null);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['message'])->toContain('null');
});

it('accepts null when nullable is true', function (): void {
    $schema = new Schema(['type' => 'string', 'nullable' => true]);

    expect(SchemaValidator::validate($schema, null))->toBe([]);
});

it('accepts null when no type is declared at all', function (): void {
    $schema = new Schema([]);

    expect(SchemaValidator::validate($schema, null))->toBe([]);
});

it('path-qualifies violations at a nested path argument', function (): void {
    $schema = new Schema(['type' => 'integer']);

    $violations = SchemaValidator::validate($schema, 'x', '/user/age');

    expect($violations[0]['path'])->toBe('/user/age');
});

it('flags a missing required property', function (): void {
    $schema = new Schema([
        'type' => 'object',
        'required' => ['id'],
        'properties' => ['id' => ['type' => 'integer']],
    ]);

    $violations = SchemaValidator::validate($schema, ['name' => 'x']);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/id')
        ->and($violations[0]['message'])->toContain('id');
});

it('recurses into a nested object property and path-qualifies the violation', function (): void {
    $schema = new Schema([
        'type' => 'object',
        'properties' => [
            'user' => [
                'type' => 'object',
                'properties' => ['age' => ['type' => 'integer']],
            ],
        ],
    ]);

    $violations = SchemaValidator::validate($schema, ['user' => ['age' => 'old']]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/user/age');
});

it('does not check a property absent from the value', function (): void {
    $schema = new Schema([
        'type' => 'object',
        'properties' => ['age' => ['type' => 'integer']],
    ]);

    expect(SchemaValidator::validate($schema, []))->toBe([]);
});

it('recurses into array items and path-qualifies by index', function (): void {
    $schema = new Schema([
        'type' => 'array',
        'items' => ['type' => 'integer'],
    ]);

    $violations = SchemaValidator::validate($schema, [1, 'two', 3]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['path'])->toBe('/1');
});

it('checks required/minItems against an empty array, ambiguous with empty object', function (): void {
    $schema = new Schema([
        'required' => ['id'],
        'minItems' => 1,
    ]);

    $violations = SchemaValidator::validate($schema, []);

    $messages = array_column($violations, 'message');
    expect($messages)->toContain('missing required property \'id\'');
});

it('flags a string not matching pattern', function (): void {
    $schema = new Schema(['type' => 'string', 'pattern' => '^[a-z]+$']);

    expect(SchemaValidator::validate($schema, 'abc'))->toBe([]);
    expect(SchemaValidator::validate($schema, 'ABC'))->toHaveCount(1);
});

it('validates known formats: date, date-time, email, uuid, uri, ipv4, ipv6', function (): void {
    $format = fn (string $fmt) => new Schema(['type' => 'string', 'format' => $fmt]);

    expect(SchemaValidator::validate($format('date'), '2024-01-15'))->toBe([]);
    expect(SchemaValidator::validate($format('date'), '2024-02-30'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('date-time'), '2024-01-15T10:00:00+00:00'))->toBe([]);
    expect(SchemaValidator::validate($format('date-time'), 'not-a-datetime'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('email'), 'a@b.com'))->toBe([]);
    expect(SchemaValidator::validate($format('email'), 'not-an-email'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('uuid'), '550e8400-e29b-41d4-a716-446655440000'))->toBe([]);
    expect(SchemaValidator::validate($format('uuid'), 'not-a-uuid'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('uri'), 'https://example.com'))->toBe([]);
    expect(SchemaValidator::validate($format('uri'), 'not a uri'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('ipv4'), '192.168.1.1'))->toBe([]);
    expect(SchemaValidator::validate($format('ipv4'), 'not-an-ip'))->toHaveCount(1);

    expect(SchemaValidator::validate($format('ipv6'), '::1'))->toBe([]);
    expect(SchemaValidator::validate($format('ipv6'), 'not-an-ip'))->toHaveCount(1);
});

it('ignores an unrecognized format rather than flagging a violation', function (): void {
    $schema = new Schema(['type' => 'string', 'format' => 'password']);

    expect(SchemaValidator::validate($schema, 'anything at all'))->toBe([]);
});

it('enforces inclusive minimum/maximum by default', function (): void {
    $schema = new Schema(['type' => 'integer', 'minimum' => 1, 'maximum' => 10]);

    expect(SchemaValidator::validate($schema, 1))->toBe([]);
    expect(SchemaValidator::validate($schema, 10))->toBe([]);
    expect(SchemaValidator::validate($schema, 0))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 11))->toHaveCount(1);
});

it('enforces exclusiveMinimum/exclusiveMaximum as OAS 3.0 booleans', function (): void {
    $schema = new Schema([
        'type' => 'integer',
        'minimum' => 1,
        'exclusiveMinimum' => true,
        'maximum' => 10,
        'exclusiveMaximum' => true,
    ]);

    expect(SchemaValidator::validate($schema, 1))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 10))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 5))->toBe([]);
});

it('enforces multipleOf', function (): void {
    $schema = new Schema(['type' => 'integer', 'multipleOf' => 5]);

    expect(SchemaValidator::validate($schema, 15))->toBe([]);
    expect(SchemaValidator::validate($schema, 16))->toHaveCount(1);
});

it('tolerates float rounding drift in multipleOf', function (): void {
    $schema = new Schema(['type' => 'number', 'multipleOf' => 0.1]);

    expect(SchemaValidator::validate($schema, 0.3))->toBe([]);
});

it('enforces minLength/maxLength', function (): void {
    $schema = new Schema(['type' => 'string', 'minLength' => 2, 'maxLength' => 4]);

    expect(SchemaValidator::validate($schema, 'abc'))->toBe([]);
    expect(SchemaValidator::validate($schema, 'a'))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, 'abcde'))->toHaveCount(1);
});

it('enforces minItems/maxItems on a non-empty array', function (): void {
    $schema = new Schema(['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 2, 'maxItems' => 3]);

    expect(SchemaValidator::validate($schema, [1, 2]))->toBe([]);
    expect(SchemaValidator::validate($schema, [1]))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, [1, 2, 3, 4]))->toHaveCount(1);
});

it('enforces minItems against a genuinely empty array', function (): void {
    $schema = new Schema(['type' => 'array', 'minItems' => 1]);

    expect(SchemaValidator::validate($schema, []))->toHaveCount(1);
});

it('enforces uniqueItems', function (): void {
    $schema = new Schema(['type' => 'array', 'items' => ['type' => 'integer'], 'uniqueItems' => true]);

    expect(SchemaValidator::validate($schema, [1, 2, 3]))->toBe([]);
    expect(SchemaValidator::validate($schema, [1, 2, 2]))->toHaveCount(1);
});

it('enforces minProperties/maxProperties', function (): void {
    $schema = new Schema(['type' => 'object', 'minProperties' => 1, 'maxProperties' => 2]);

    expect(SchemaValidator::validate($schema, ['a' => 1]))->toBe([]);
    expect(SchemaValidator::validate($schema, []))->toHaveCount(1);
    expect(SchemaValidator::validate($schema, ['a' => 1, 'b' => 2, 'c' => 3]))->toHaveCount(1);
});
