<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Expression\JsonPathEvaluator;

it('extracts using jsonpath', function (): void {
    $data = ['users' => [['id' => 1], ['id' => 2]]];

    expect(JsonPathEvaluator::evaluate('$.users[*].id', $data))->toEqual([1, 2]);
});
