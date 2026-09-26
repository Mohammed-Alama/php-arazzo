<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Selector;

it('models a GraphQL operation object', function (): void {
    $op = new GraphQlOperation(
        schema: '$sourceDescriptions.gql',
        operation: 'query GetToken { token }',
        extensions: ['contentType' => 'application/json'],
    );

    expect($op->schema)->toBe('$sourceDescriptions.gql');
    expect($op->operation)->toBe('query GetToken { token }');
    expect($op->extensions)->toBe(['contentType' => 'application/json']);
    expect($op->extensionsSelector)->toBeNull();
});

it('accepts an extensionsSelector form', function (): void {
    $op = new GraphQlOperation(
        schema: '$sourceDescriptions.gql',
        operation: 'query GetToken { token }',
        extensionsSelector: new Selector(null, '$sourceDescriptions.gql.url#/operations/0', ExpressionType::JsonPointer),
        extensions: null,
    );

    expect($op->extensionsSelector?->type)->toBe(ExpressionType::JsonPointer);
});
