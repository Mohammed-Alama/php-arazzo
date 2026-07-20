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
