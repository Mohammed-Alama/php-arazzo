<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\SourceGraph;
use Alama\Arazzo\Tests\Support\Fx;

it('normalizes document sources through the registry', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'facade_').'.json';
    file_put_contents($file, json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.0'],
        'servers' => [['url' => 'https://example.test']],
        'paths' => ['/pets' => ['get' => ['operationId' => 'listPets', 'responses' => ['200' => ['description' => 'OK']]]]],
    ]));

    try {
        $loader = SourceGraph::loader();
        $index = $loader->normalizeSources(Fx::doc(
            sources: [new SourceDescription('pets-api', $file, SourceType::Openapi)],
        ));

        expect($index['$sourceDescriptions.pets-api.listPets'])->toBeInstanceOf(ResolvedOperation::class);
    } finally {
        @unlink($file);
    }
});
