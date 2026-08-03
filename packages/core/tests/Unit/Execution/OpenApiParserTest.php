<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Execution\OpenApiParser;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use PHPUnit\Framework\TestCase;

class OpenApiParserTest extends TestCase
{
    public function test_finds_method_path_and_operation_by_operation_id(): void
    {
        $openApi = new OpenApi([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'paths' => [
                '/pets/{id}' => [
                    'get' => ['operationId' => 'getPet', 'responses' => []],
                ],
            ],
        ]);

        [$method, $path, $operation] = OpenApiParser::findOperation($openApi, 'getPet');

        $this->assertSame('GET', $method);
        $this->assertSame('/pets/{id}', $path);
        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('getPet', $operation->operationId);
    }

    public function test_throws_when_operation_not_found(): void
    {
        $openApi = new OpenApi([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test', 'version' => '1.0'],
            'paths' => [],
        ]);

        $this->expectException(\RuntimeException::class);
        OpenApiParser::findOperation($openApi, 'missingOp');
    }
}
