<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Plugins;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Expression\JsonPathEvaluator;
use Alama\Arazzo\Expression\Plugins\JsonPathSelectorPlugin;

it('supports only the jsonpath expression type', function () {
    $plugin = new JsonPathSelectorPlugin();

    expect($plugin->supports(ExpressionType::JsonPath))->toBeTrue();
    expect($plugin->supports(ExpressionType::XPath))->toBeFalse();
    expect($plugin->supports(ExpressionType::JsonPointer))->toBeFalse();
});

it('advertises rfc9535 as the supported version', function () {
    $plugin = new JsonPathSelectorPlugin();

    expect($plugin->supportedVersions())->toBe(['rfc9535']);
});

it('returns null for a non-array/object root value', function () {
    $plugin = new JsonPathSelectorPlugin();

    expect($plugin->evaluate('not-a-root', '$.x', null))->toBeNull();
    expect($plugin->evaluate(42, '$.x', null))->toBeNull();
    expect($plugin->evaluate(null, '$.x', null))->toBeNull();
});

it('delegates evaluation to its evaluator', function () {
    $evaluator = new JsonPathEvaluator();
    $plugin = new JsonPathSelectorPlugin($evaluator);

    expect($plugin->evaluate(['a' => 1], '$.a', null))->toBe(1);
});
