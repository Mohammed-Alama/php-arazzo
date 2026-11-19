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
use Alama\Arazzo\Document\Validator\Rules\StepParameterDuplicateRule;
use Alama\Arazzo\Tests\Support\Fx;

it('rejects duplicate parameters within same step (same name and in)', function (): void {
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
                        new Parameter('petId', ParameterIn::Path, 'y'),
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
    (new StepParameterDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1);
});

it('accepts different parameter names in same step', function (): void {
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
                        new Parameter('name', ParameterIn::Query, 'y'),
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
    (new StepParameterDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('accepts duplicate params across different steps', function (): void {
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
                    Fx::step('s1', 'op', params: [
                        new Parameter('petId', ParameterIn::Path, 'x'),
                    ]),
                    Fx::step('s2', 'op', params: [
                        new Parameter('petId', ParameterIn::Path, 'y'),
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
    (new StepParameterDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('rejects duplicate workflow-level parameters', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('T', null, null, '1'),
        sourceDescriptions: [],
        workflows: [
            new Workflow(
                workflowId: 'wf',
                summary: null,
                description: null,
                inputs: null,
                dependsOn: [],
                steps: [],
                successActions: [],
                failureActions: [],
                outputs: [],
                parameters: [
                    new Parameter('petId', ParameterIn::Path, 'x'),
                    new Parameter('petId', ParameterIn::Path, 'y'),
                ],
            ),
        ],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_0,
    );
    (new StepParameterDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1);
});

it('accepts reusable parameter duplicates', function (): void {
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
    (new StepParameterDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('accepts parameter with same name but different in values', function (): void {
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
                        new Parameter('petId', ParameterIn::Query, 'y'),
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
    (new StepParameterDuplicateRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});
