<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepParameterRequiresInRule;
use Alama\Arazzo\Tests\Support\Fx;

it('rejects operation parameter without in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [
            new Workflow(
                workflowId: 'w',
                summary: null,
                description: null,
                inputs: null,
                dependsOn: [],
                steps: [
                    Fx::step('s', 'op', params: [
                        new Parameter('petId', null, 'x'),
                    ]),
                ],
                successActions: [],
                failureActions: [],
                outputs: [],
                parameters: [],
            ),
        ],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_0,
    );
    (new StepParameterRequiresInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1);
});

it('accepts operation parameter with in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [
            new Workflow(
                workflowId: 'w',
                summary: null,
                description: null,
                inputs: null,
                dependsOn: [],
                steps: [
                    Fx::step('s', 'op', params: [
                        new Parameter('petId', ParameterIn::Path, 'x'),
                    ]),
                ],
                successActions: [],
                failureActions: [],
                outputs: [],
                parameters: [],
            ),
        ],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_0,
    );
    (new StepParameterRequiresInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('skips reusable parameter without in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [
            new Workflow(
                workflowId: 'w',
                summary: null,
                description: null,
                inputs: null,
                dependsOn: [],
                steps: [
                    Fx::step('s', 'op', params: [
                        new Reusable('$components.parameters.x'),
                    ]),
                ],
                successActions: [],
                failureActions: [],
                outputs: [],
                parameters: [],
            ),
        ],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_0,
    );
    (new StepParameterRequiresInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});
