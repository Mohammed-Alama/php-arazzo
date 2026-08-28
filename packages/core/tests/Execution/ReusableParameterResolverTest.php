<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Execution\ReusableParameterResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\SourceDescription;
use RuntimeException;

function resolverDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.1',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', 'https://api.test', SourceType::Openapi)],
        workflows: [],
        components: new Components([], [
            'page' => new Parameter(name: 'limit', in: ParameterIn::Query, value: 25),
            'auth' => new Parameter(name: 'Authorization', in: ParameterIn::Header, value: new Expression('{$inputs.token}')),
        ], [], []),
        specificationExtensions: [],
    );
}

it('passes concrete parameters through untouched', function (): void {
    $resolver = new ReusableParameterResolver();
    $param = new Parameter(name: 'q', in: ParameterIn::Query, value: 'cats');

    expect($resolver->resolve([$param], null))->toBe([$param]);
});

it('substitutes a reusable parameter with its component declaration', function (): void {
    $resolver = new ReusableParameterResolver();
    $reusable = new Reusable(reference: '$components.parameters.page');

    $resolved = $resolver->resolve([$reusable], resolverDocument());

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]->name)->toBe('limit')
        ->and($resolved[0]->in)->toBe(ParameterIn::Query)
        ->and($resolved[0]->value)->toBe(25);
});

it('lets an inline value on the reusable override the component default', function (): void {
    $resolver = new ReusableParameterResolver();
    $reusable = new Reusable(reference: '$components.parameters.page', value: 50);

    $resolved = $resolver->resolve([$reusable], resolverDocument());

    expect($resolved[0]->value)->toBe(50)
        ->and($resolved[0]->name)->toBe('limit');
});

it('throws for unresolvable references or malformed ones', function (string $reference): void {
    (new ReusableParameterResolver())->resolve(
        [new Reusable(reference: $reference)],
        resolverDocument(),
    );
})->throws(RuntimeException::class, 'Unresolvable reusable parameter reference')->with([
    '$components.parameters.missing',
    '$components.inputs.page',
    'components.parameters.page',
]);

it('resolves mixed lists preserving order', function (): void {
    $resolver = new ReusableParameterResolver();
    $direct = new Parameter(name: 'q', in: ParameterIn::Query, value: 'x');
    $reused = new Reusable(reference: '$components.parameters.page');

    $resolved = $resolver->resolve([$direct, $reused], resolverDocument());

    expect(array_map(fn ($p) => $p->name, $resolved))->toBe(['q', 'limit']);
});
