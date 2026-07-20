<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\JsonPathEvaluator;
use PHPUnit\Framework\TestCase;

class JsonPathEvaluatorTest extends TestCase
{
    public function test_extracts_using_jsonpath(): void
    {
        $data = ['users' => [['id' => 1], ['id' => 2]]];
        $result = JsonPathEvaluator::evaluate('$.users[*].id', $data);
        $this->assertEquals([1, 2], $result);
    }
}
