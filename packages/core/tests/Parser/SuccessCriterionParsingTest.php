<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Parser;

use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\Arazzo\Parser\ParseContext;
use Alama\Arazzo\Parser\Parser;

function parseSuccessCriterion(array $node)
{
    $parser = new Parser();
    $ctx = new ParseContext('test.yaml');
    $method = new \ReflectionMethod($parser, 'parseSuccessCriterion');
    $method->setAccessible(true);

    return $method->invoke($parser, $node, $ctx);
}

it('parses a bare string type with version null', function (): void {
    $criterion = parseSuccessCriterion(['condition' => '$statusCode == 200', 'type' => 'simple']);

    expect($criterion->type)->toBe(CriterionType::Simple)
        ->and($criterion->version)->toBeNull();
});

it('parses the {type, version} object form', function (): void {
    $criterion = parseSuccessCriterion([
        'condition' => "$.status == 'CREATED'",
        'type' => ['type' => 'jsonpath', 'version' => 'rfc9535'],
    ]);

    expect($criterion->type)->toBe(CriterionType::JsonPath)
        ->and($criterion->version)->toBe('rfc9535');
});

it('parses a criterion with no type at all', function (): void {
    $criterion = parseSuccessCriterion(['condition' => '$statusCode == 200']);

    expect($criterion->type)->toBeNull()
        ->and($criterion->version)->toBeNull();
});
