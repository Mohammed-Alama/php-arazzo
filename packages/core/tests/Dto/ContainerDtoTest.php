<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Dto;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;

it('builds full document tree', function (): void {
    $step = new Step('s1', null, 'getFoo', null, null, [], null, [], [], [], []);
    $wf = new Workflow('wf', null, null, null, [], [$step], [], [], [], []);
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [new SourceDescription('api', '/x', SourceType::Openapi)],
        workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );

    expect($doc->workflows[0]->steps[0]->stepId)->toBe('s1');
});
