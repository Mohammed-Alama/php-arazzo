<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use InvalidArgumentException;

beforeEach(function (): void {
    $this->normalizer = new OpenApi30Normalizer();
});

it('throws if path not found', function (): void {
    $document = ['paths' => []];

    $this->normalizer->normalize($document, '/users', 'get');
})->throws(InvalidArgumentException::class, "Path '/users' not found in document.");

it('throws if method not found', function (): void {
    $document = [
        'paths' => [
            '/users' => [
                'get' => [],
            ],
        ],
    ];

    $this->normalizer->normalize($document, '/users', 'post');
})->throws(InvalidArgumentException::class, "Method 'post' not found for path '/users'.");

it('resolves server precedence', function (): void {
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
    expect($docOp->resolvedServerUrl)->toBe('https://doc.server');

    $pathOp = $this->normalizer->normalize($document, '/path', 'get');
    expect($pathOp->resolvedServerUrl)->toBe('https://path.server');

    $opOp = $this->normalizer->normalize($document, '/op', 'get');
    expect($opOp->resolvedServerUrl)->toBe('https://op.server');
});

it('resolves parameters with refs and overrides', function (): void {
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

    expect($op->pathParameters)->toHaveCount(1)
        ->and($op->headerParameters)->toHaveCount(1)
        ->and($op->queryParameters)->toHaveCount(0)
        ->and($op->cookieParameters)->toHaveCount(0)
        ->and($op->pathParameters)->toHaveKey('userId')
        ->and($op->headerParameters)->toHaveKey('X-Token')
        ->and($op->pathParameters['userId']['description'])->toBe('Overridden description')
        ->and($op->headerParameters['X-Token']['in'])->toBe('header');
});

it('resolves request body with ref', function (): void {
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

    // Ensure $ref is removed at the requestBody level
    expect($op->requestBodies)->toHaveCount(1)
        ->and($op->requestBodies)->toHaveKey('application/json')
        ->and($op->requestBodies['application/json']['schema'])->toEqual(['$ref' => '#/components/schemas/User']);
});

it('does not resolve responses with ref', function (): void {
    $document = [
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

    expect($op->responses)->toHaveCount(2)
        ->and($op->responses['200']['description'])->toBe('Success')
        ->and($op->responses['404']['$ref'])->toBe('#/components/responses/NotFound');
});
