<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Http\Controllers;

use Alama\Arazzo\Cli\Generator\ArazzoGenerator;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver;
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

it('shapes a graph into a natural language trace', function () {
    $generator = Mockery::mock(ArazzoGenerator::class);
    $trace = '';
    $generator->shouldReceive('generate')
        ->once()
        ->with('fake.yaml', Mockery::on(function (string $value) use (&$trace): bool {
            $trace = $value;

            return true;
        }))
        ->andReturn('generated_yaml');
    $this->app->instance(ArazzoGenerator::class, $generator);

    postJson('/api/arazzo/generate', [
        'openapi' => 'fake.yaml',
        'graph' => [
            'nodes' => [
                [
                    'id' => 'n1',
                    'data' => [
                        'method' => 'GET',
                        'path' => '/rides',
                        'operationId' => 'listRides',
                        'parameters' => "page\nsize",
                        'criteria' => 'status == 200',
                    ],
                ],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2'],
            ],
        ],
    ])
        ->assertStatus(200)
        ->assertJson(['yaml' => 'generated_yaml']);

    expect($trace)->toBe(
        "Workflow Graph Intent:\n"
        ."- Node n1: Execute GET /rides (Operation: listRides).\n"
        ."  - Parameters: page size\n"
        ."  - Success Criteria: status == 200\n"
        ."- Edge: Output of Node n1 flows to Node n2.\n",
    );
});

it('tolerates malformed graph entries', function () {
    $generator = Mockery::mock(ArazzoGenerator::class);
    $trace = '';
    $generator->shouldReceive('generate')
        ->once()
        ->with('fake.yaml', Mockery::on(function (string $value) use (&$trace): bool {
            $trace = $value;

            return true;
        }))
        ->andReturn('generated_yaml');
    $this->app->instance(ArazzoGenerator::class, $generator);

    postJson('/api/arazzo/generate', [
        'openapi' => 'fake.yaml',
        'graph' => [
            'nodes' => [
                ['id' => 'n1', 'data' => ['method' => 'GET', 'path' => '/rides', 'operationId' => 'listRides']],
                'not-a-record',
                ['id' => 'n2', 'data' => 'not-a-map'],
                ['id' => ['not', 'a', 'string'], 'data' => ['method' => ['not', 'a', 'string']]],
            ],
            'edges' => 'not-a-list',
        ],
    ])
        ->assertStatus(200)
        ->assertJson(['yaml' => 'generated_yaml']);

    expect($trace)->toBe(
        "Workflow Graph Intent:\n"
        ."- Node n1: Execute GET /rides (Operation: listRides).\n"
        ."- Node n2: Execute   (Operation: ).\n"
        ."- Node : Execute   (Operation: ).\n",
    );
});
