<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Dto;

use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\Reusable;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

it('maps enum spec strings', function (): void {
    expect(SourceType::from('openapi'))->toBe(SourceType::Openapi)
        ->and(ParameterIn::from('query'))->toBe(ParameterIn::Query)
        ->and(CriterionType::from('jsonpath'))->toBe(CriterionType::JsonPath);
});

it('builds Info', function (): void {
    $info = new Info('T', null, 'd', '1.0');
    expect($info->title)->toBe('T')->and($info->version)->toBe('1.0');
});

it('builds SourceDescription', function (): void {
    $s = new SourceDescription('api', '/x.yaml', SourceType::Openapi);
    expect($s->name)->toBe('api');
});

it('builds Parameter with expression value', function (): void {
    $p = new Parameter('id', ParameterIn::Query, new Expression('{$inputs.id}'));
    expect($p->name)->toBe('id')
        ->and($p->value)->toBeInstanceOf(Expression::class);
});

it('builds RequestBody with replacements', function (): void {
    $rb = new RequestBody('application/json', ['a' => 1], [
        new PayloadReplacement('/a', 2),
    ]);
    expect($rb->replacements)->toHaveCount(1)
        ->and($rb->replacements[0]->target)->toBe('/a');
});

it('builds SuccessCriterion', function (): void {
    $c = new SuccessCriterion('$response.body', '$.id != null', CriterionType::JsonPath);
    expect($c->condition)->toBe('$.id != null');
});

it('builds Reusable', function (): void {
    $r = new Reusable('$components.parameters.foo');
    expect($r->reference)->toStartWith('$components.');
});

it('stores raw Expression string', function (): void {
    $e = new Expression('{$inputs.name}');
    expect($e->raw)->toBe('{$inputs.name}');
});
