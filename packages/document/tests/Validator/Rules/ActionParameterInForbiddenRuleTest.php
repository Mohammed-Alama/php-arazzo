<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
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
use Alama\Arazzo\Document\Validator\Rules\ActionParameterInForbiddenRule;
use Alama\Arazzo\Tests\Support\Fx;

it('rejects in on success action', function (): void {
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
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('action.parameter_in_forbidden')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/onSuccess/0/parameters/0/in');
});

it('rejects in on failure action', function (): void {
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
                                new Parameter('petId', ParameterIn::Header, 'x'),
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
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('action.parameter_in_forbidden')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/onFailure/0/parameters/0/in');
});

it('accepts reusable action parameter', function (): void {
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
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('rejects parameters on 10 action', function (): void {
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
                    Fx::step('s', 'op', onSuccess: [
                        new SuccessGotoAction(
                            'goto',
                            stepId: null,
                            workflowId: 'other',
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
        specVersion: SpecVersion::V1_0,
    );
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('action.parameter_in_forbidden')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/steps/0/onSuccess/0/parameters');
});

it('accepts parameter without in', function (): void {
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
                                new Parameter('petId', null, 'x'),
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
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});

it('rejects in on workflow-level successAction', function (): void {
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
                steps: [],
                successActions: [
                    new SuccessGotoAction(
                        'goto',
                        stepId: null,
                        workflowId: null,
                        criteria: [],
                        parameters: [
                            new Parameter('petId', ParameterIn::Query, 'x'),
                        ],
                    ),
                ],
                failureActions: [],
                outputs: [],
                parameters: [],
            ),
        ],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('action.parameter_in_forbidden')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/successActions/0/parameters/0/in');
});

it('rejects in on workflow-level failureAction', function (): void {
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
                steps: [],
                successActions: [],
                failureActions: [
                    new FailureGotoAction(
                        'goto',
                        stepId: null,
                        workflowId: null,
                        criteria: [],
                        parameters: [
                            new Parameter('petId', ParameterIn::Header, 'x'),
                        ],
                    ),
                ],
                outputs: [],
                parameters: [],
            ),
        ],
        components: new Components([], [], [], []),
        specificationExtensions: [],
        specVersion: SpecVersion::V1_1,
    );
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(1)
        ->and($errors->errors()[0]->code)->toBe('action.parameter_in_forbidden')
        ->and($errors->errors()[0]->path)->toBe('/workflows/0/failureActions/0/parameters/0/in');
});

it('accepts end action without action parameters', function (): void {
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
    (new ActionParameterInForbiddenRule())->check($doc, SymbolTable::build($doc), $errors);
    expect($errors->errors())->toHaveCount(0);
});
