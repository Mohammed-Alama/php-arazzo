<?php

namespace Alama\LaravelArazzo\Tests\Http\Controllers;

use Alama\LaravelArazzo\Resolution\SourceResolver;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use Mockery;
use Alama\LaravelArazzo\Tests\TestCase;

uses(TestCase::class);

it('returns endpoints list from openapi spec', function () {
    $resolver = Mockery::mock(SourceResolver::class);
    $resolved = Mockery::mock(ResolvedSource::class);
    
    $resolved->shouldReceive('extract')->with('/')->andReturn([
        'paths' => [
            '/test' => [
                'get' => ['operationId' => 'getTest', 'summary' => 'Test', 'tags' => ['API']]
            ]
        ]
    ]);
    
    $resolver->shouldReceive('resolve')->andReturn($resolved);
    $this->app->instance(SourceResolver::class, $resolver);
    
    getJson('/api/arazzo/endpoints?spec=fake.yaml')
        ->assertStatus(200)
        ->assertJson([
            ['method' => 'GET', 'path' => '/test', 'operationId' => 'getTest']
        ]);
});

it('generates yaml from graph', function () {
    $generator = Mockery::mock(ArazzoGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn('generated_yaml');
    $this->app->instance(ArazzoGenerator::class, $generator);
    
    postJson('/api/arazzo/generate', [
        'openapi' => 'fake.yaml',
        'graph' => ['nodes' => [], 'edges' => []]
    ])
        ->assertStatus(200)
        ->assertJson(['yaml' => 'generated_yaml']);
});
