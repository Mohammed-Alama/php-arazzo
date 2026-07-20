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
