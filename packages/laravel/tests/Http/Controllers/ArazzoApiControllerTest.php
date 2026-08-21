<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Http\Controllers;

use Alama\Arazzo\Generator\ArazzoGenerator;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\SourceDocument;
use Mockery;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('returns endpoints list from openapi spec', function () {
    $resolver = Mockery::mock(SourceResolver::class);

    $resolved = new SourceDocument(
        name: 'test',
        type: SourceType::Openapi,
        canonicalUri: 'http://test',
        content: [
            'paths' => [
                '/test' => [
                    'get' => ['operationId' => 'getTest', 'summary' => 'Test', 'tags' => ['API']],
                ],
            ],
        ],
    );

    $resolver->shouldReceive('resolve')->andReturn($resolved);
    $this->app->instance(SourceResolver::class, $resolver);

    getJson('/api/arazzo/endpoints?spec=fake.yaml')
        ->assertStatus(200)
        ->assertJson([
            ['method' => 'GET', 'path' => '/test', 'operationId' => 'getTest'],
        ]);
});

it('generates yaml from graph', function () {
    $generator = Mockery::mock(ArazzoGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn('generated_yaml');
    $this->app->instance(ArazzoGenerator::class, $generator);

    postJson('/api/arazzo/generate', [
        'openapi' => 'fake.yaml',
        'graph' => ['nodes' => [], 'edges' => []],
    ])
        ->assertStatus(200)
        ->assertJson(['yaml' => 'generated_yaml']);
});
