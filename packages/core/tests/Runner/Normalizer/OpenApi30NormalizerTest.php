<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Normalizer;

use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OpenApi30NormalizerTest extends TestCase
{
    private OpenApi30Normalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new OpenApi30Normalizer();
    }

    public function test_throws_if_path_not_found(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Path '/users' not found in document.");

        $document = ['paths' => []];
        $this->normalizer->normalize($document, '/users', 'get');
    }

    public function test_throws_if_method_not_found(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Method 'post' not found for path '/users'.");

        $document = [
            'paths' => [
                '/users' => [
                    'get' => [],
                ],
            ],
        ];
        $this->normalizer->normalize($document, '/users', 'post');
    }

    public function test_resolves_server_precedence(): void
    {
        $document = [
            'servers' => [['url' => 'https://doc.server']],
            'paths' => [
                '/doc' => [
                    'get' => [],
                ],
                '/path' => [
                    'servers' => [['url' => 'https://path.server']],
                    'get' => [],
                ],
                '/op' => [
                    'servers' => [['url' => 'https://path.server']],
                    'get' => [
                        'servers' => [['url' => 'https://op.server']],
                    ],
                ],
            ],
        ];

        $docOp = $this->normalizer->normalize($document, '/doc', 'get');
        $this->assertEquals('https://doc.server', $docOp->resolvedServerUrl);

        $pathOp = $this->normalizer->normalize($document, '/path', 'get');
        $this->assertEquals('https://path.server', $pathOp->resolvedServerUrl);

        $opOp = $this->normalizer->normalize($document, '/op', 'get');
        $this->assertEquals('https://op.server', $opOp->resolvedServerUrl);
    }

    public function test_resolves_parameters_with_refs_and_overrides(): void
    {
        $document = [
            'components' => [
                'parameters' => [
                    'UserId' => [
                        'name' => 'userId',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer'],
                    ],
                    'HeaderToken' => [
                        'name' => 'X-Token',
                        'in' => 'header',
                        'required' => false,
                        'schema' => ['type' => 'string'],
                    ],
                ],
            ],
            'paths' => [
                '/users/{userId}' => [
                    'parameters' => [
                        ['$ref' => '#/components/parameters/UserId'],
                    ],
                    'get' => [
                        'parameters' => [
                            ['$ref' => '#/components/parameters/HeaderToken'],
                            // Override path parameter
                            [
                                'name' => 'userId',
                                'in' => 'path',
                                'description' => 'Overridden description',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $op = $this->normalizer->normalize($document, '/users/{userId}', 'get');

        $this->assertCount(2, $op->parameters);

        // Find userId param
        $userIdParam = null;
        $headerParam = null;
        foreach ($op->parameters as $p) {
            if ($p['name'] === 'userId') {
                $userIdParam = $p;
            } elseif ($p['name'] === 'X-Token') {
                $headerParam = $p;
            }
        }

        $this->assertNotNull($userIdParam);
        $this->assertEquals('Overridden description', $userIdParam['description']);

        $this->assertNotNull($headerParam);
        $this->assertEquals('header', $headerParam['in']);
    }

    public function test_resolves_request_body_with_ref(): void
    {
        $document = [
            'components' => [
                'requestBodies' => [
                    'UserBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/User',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/users' => [
                    'post' => [
                        'requestBody' => [
                            '$ref' => '#/components/requestBodies/UserBody',
                        ],
                    ],
                ],
            ],
        ];

        $op = $this->normalizer->normalize($document, '/users', 'post');

        $this->assertCount(1, $op->requestBodies);
        $this->assertArrayHasKey('application/json', $op->requestBodies);
        // Ensure $ref is removed at the requestBody level
        $this->assertEquals(['$ref' => '#/components/schemas/User'], $op->requestBodies['application/json']['schema']);
    }

    public function test_resolves_responses_with_ref(): void
    {
        $document = [
            'components' => [
                'responses' => [
                    'NotFound' => [
                        'description' => 'Not found',
                    ],
                ],
            ],
            'paths' => [
                '/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                            ],
                            '404' => [
                                '$ref' => '#/components/responses/NotFound',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $op = $this->normalizer->normalize($document, '/users', 'get');

        $this->assertCount(2, $op->responses);
        $this->assertEquals('Success', $op->responses['200']['description']);
        $this->assertEquals('Not found', $op->responses['404']['description']);
        $this->assertArrayNotHasKey('$ref', $op->responses['404']);
    }
}
