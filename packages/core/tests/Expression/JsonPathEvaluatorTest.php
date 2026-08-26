<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Expression\JsonPathEvaluator;

it('evaluates simple jsonpath expression', function () {
    $data = [
        'store' => [
            'book' => [
                ['category' => 'reference', 'author' => 'Nigel Rees', 'title' => 'Sayings of the Century', 'price' => 8.95],
                ['category' => 'fiction', 'author' => 'Evelyn Waugh', 'title' => 'Sword of Honour', 'price' => 12.99],
            ],
            'bicycle' => ['color' => 'red', 'price' => 19.95],
        ],
    ];

    // Test single item return unwrapping
    $result = JsonPathEvaluator::evaluate('$.store.bicycle.color', $data);
    expect($result)->toBe('red');

    // Test array return
    $result = JsonPathEvaluator::evaluate('$.store.book[*].author', $data);
    expect($result)->toBe(['Nigel Rees', 'Evelyn Waugh']);
});

it('handles invalid or unmatched jsonpath gracefully', function () {
    $data = ['user' => ['name' => 'Alice']];

    $result = JsonPathEvaluator::evaluate('$.user.age', $data);
    expect($result)->toBe([]);
});
