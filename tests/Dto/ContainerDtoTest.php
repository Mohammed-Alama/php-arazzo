<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Dto;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;

it('builds full document tree', function (): void {
    $step = new Step('s1', null, 'getFoo', null, null, [], null, [], [], [], []);
    $wf   = new Workflow('wf', null, null, null, [], [$step], [], [], [], []);
    $doc  = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', '/x', SourceType::Openapi)],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    expect($doc->workflows[0]->steps[0]->stepId)->toBe('s1');
});
