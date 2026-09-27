<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;

function resumerDocument(): array
{
    $definitionRegistry = new InMemoryDefinitionRegistry();
    $step = new Step('wait-for-ride', null, StepTarget::async('receive', 'channels/rides/created'), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [$workflow], new Components([], [], [], []), []);
    $definitionId = $definitionRegistry->register($document);

    return [$definitionRegistry, $definitionId, $workflow, $step];
}
