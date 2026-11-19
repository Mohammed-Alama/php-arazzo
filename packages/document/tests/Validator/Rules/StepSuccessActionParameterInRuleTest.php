<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
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
use Alama\Arazzo\Document\Validator\Rules\StepSuccessActionParameterInRule;
use Alama\Arazzo\Tests\Support\Fx;

it('rejects success action parameter with in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
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
                    Fx::step('s', 'op', onSuccess: [
                        new SuccessGotoAction(
                            'goto',
                            stepId: null,
                            workflowId: null,
                            criteria: [],
                            parameters: [
                                new Parameter('petId', ParameterIn::Query, 'x'),
                            ],
                        ),
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
        specVersion: SpecVersion::V1_1,
    );
    (new StepSuccessActionParameterInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1);
});

it('accepts success action parameter without in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
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
                    Fx::step('s', 'op', onSuccess: [
                        new SuccessGotoAction(
                            'goto',
                            stepId: null,
                            workflowId: null,
                            criteria: [],
                            parameters: [],
                        ),
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
        specVersion: SpecVersion::V1_1,
    );
    (new StepSuccessActionParameterInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('accepts failure action parameter with in (only success actions checked)', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
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
                    Fx::step('s', 'op', onFailure: [
                        new FailureGotoAction(
                            'goto',
                            stepId: null,
                            workflowId: null,
                            criteria: [],
                            parameters: [
                                new Parameter('petId', ParameterIn::Query, 'x'),
                            ],
                        ),
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
        specVersion: SpecVersion::V1_1,
    );
    (new StepSuccessActionParameterInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('accepts reusable parameter without in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
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
                    Fx::step('s', 'op', onSuccess: [
                        new SuccessGotoAction(
                            'goto',
                            stepId: null,
                            workflowId: null,
                            criteria: [],
                            parameters: [
                                new Reusable('$components.parameters.x'),
                            ],
                        ),
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
        specVersion: SpecVersion::V1_1,
    );
    (new StepSuccessActionParameterInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('accepts end action parameter without in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
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
                    Fx::step('s', 'op', onSuccess: [
                        new SuccessEndAction(
                            'end',
                            criteria: [],
                        ),
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
        specVersion: SpecVersion::V1_1,
    );
    (new StepSuccessActionParameterInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('accepts invoke action parameter without in', function (): void {
    $errors = new ErrorCollector();
    $doc = new ArazzoDocument(
        arazzo: '1.1.0',
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
                    Fx::step('s', 'op', onSuccess: [
                        new SubWorkflowSuccessAction(
                            'invoke',
                            workflowId: 'other',
                            criteria: [],
                            parameters: [],
                        ),
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
        specVersion: SpecVersion::V1_1,
    );
    (new StepSuccessActionParameterInRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});
