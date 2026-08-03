<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Parser;

use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Exceptions\ParserException;
use Alama\Arazzo\Parser\ParseContext;
use Alama\Arazzo\Parser\Parser;

class LeafProbe extends Parser
{
    public function pInfo(mixed $n, ParseContext $c)
    {
        return $this->parseInfo($n, $c);
    }

    public function pSrc(mixed $n, ParseContext $c)
    {
        return $this->parseSourceDescription($n, $c);
    }

    public function pParam(mixed $n, ParseContext $c)
    {
        return $this->parseParameter($n, $c);
    }

    public function pRepl(mixed $n, ParseContext $c)
    {
        return $this->parsePayloadReplacement($n, $c);
    }

    public function pReq(mixed $n, ParseContext $c)
    {
        return $this->parseRequestBody($n, $c);
    }

    public function pCrit(mixed $n, ParseContext $c)
    {
        return $this->parseSuccessCriterion($n, $c);
    }

    public function pReu(mixed $n, ParseContext $c)
    {
        return $this->parseReusable($n, $c);
    }

    public function pExpr(mixed $n)
    {
        return $this->parseExpressionOrValue($n);
    }
}

$ctx = fn () => new ParseContext('/x');

it('parses Info', function () use ($ctx): void {
    $i = (new LeafProbe())->pInfo(['title' => 'T', 'version' => '1.0'], $ctx());
    expect($i->title)->toBe('T')->and($i->version)->toBe('1.0')->and($i->summary)->toBeNull();
});

it('rejects Info missing version', function () use ($ctx): void {
    (new LeafProbe())->pInfo(['title' => 'T'], $ctx());
})->throws(ParserException::class);

it('parses SourceDescription', function () use ($ctx): void {
    $s = (new LeafProbe())->pSrc(['name' => 'api', 'url' => '/x', 'type' => 'openapi'], $ctx());
    expect($s->type)->toBe(SourceType::Openapi);
});

it('rejects bad source type', function () use ($ctx): void {
    (new LeafProbe())->pSrc(['name' => 'api', 'url' => '/x', 'type' => 'graphql'], $ctx());
})->throws(ParserException::class);

it('parses Parameter with expression value', function () use ($ctx): void {
    $p = (new LeafProbe())->pParam(['name' => 'id', 'in' => 'query', 'value' => '{$inputs.id}'], $ctx());
    expect($p->in)->toBe(ParameterIn::Query)
        ->and($p->value)->toBeInstanceOf(Expression::class);
});

it('parses scalar Parameter value', function () use ($ctx): void {
    $p = (new LeafProbe())->pParam(['name' => 'id', 'value' => 42], $ctx());
    expect($p->value)->toBe(42);
});

it('parses array Parameter value', function () use ($ctx): void {
    $p = (new LeafProbe())->pParam(['name' => 'id', 'value' => ['a' => 'b']], $ctx());
    expect($p->value)->toBe(['a' => 'b']);
});

it('parses SuccessCriterion with type', function () use ($ctx): void {
    $c = (new LeafProbe())->pCrit(['condition' => '$.id != null', 'type' => 'jsonpath', 'context' => '$response.body'], $ctx());
    expect($c->type)->toBe(CriterionType::JsonPath);
});

it('parses Reusable', function () use ($ctx): void {
    $r = (new LeafProbe())->pReu(['reference' => '$components.parameters.x'], $ctx());
    expect($r->reference)->toBe('$components.parameters.x');
});

it('detects expression strings or values', function (): void {
    expect((new LeafProbe())->pExpr('{$inputs.x}'))->toBeInstanceOf(Expression::class)
        ->and((new LeafProbe())->pExpr('plain'))->toBe('plain')
        ->and((new LeafProbe())->pExpr(5))->toBe(5)
        ->and((new LeafProbe())->pExpr(['a' => 1]))->toBe(['a' => 1])
        ->and((new LeafProbe())->pExpr(null))->toBeNull();
});

it('parses PayloadReplacement', function () use ($ctx): void {
    $r = (new LeafProbe())->pRepl(['target' => '$request.body.foo', 'value' => 'bar'], $ctx());
    expect($r->target)->toBe('$request.body.foo')
        ->and($r->value)->toBe('bar');
});

it('rejects PayloadReplacement missing value', function () use ($ctx): void {
    (new LeafProbe())->pRepl(['target' => '$request.body.foo'], $ctx());
})->throws(ParserException::class);

it('parses RequestBody without replacements', function () use ($ctx): void {
    $b = (new LeafProbe())->pReq(['contentType' => 'application/json', 'payload' => ['a' => 1]], $ctx());
    expect($b->contentType)->toBe('application/json')
        ->and($b->payload)->toBe(['a' => 1])
        ->and($b->replacements)->toBe([]);
});

it('parses RequestBody with replacements', function () use ($ctx): void {
    $b = (new LeafProbe())->pReq([
        'payload' => '{$inputs.body}',
        'replacements' => [['target' => '$request.body.foo', 'value' => 'bar']],
    ], $ctx());

    expect($b->payload)->toBeInstanceOf(Expression::class)
        ->and($b->replacements)->toHaveCount(1)
        ->and($b->replacements[0]->target)->toBe('$request.body.foo');
});
