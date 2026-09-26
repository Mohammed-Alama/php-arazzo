<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\InteractionStepRule;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts a form interaction with a prompt and input schema', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'Confirm', inputSchema: ['type' => 'object'], mode: InteractionMode::Form),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('accepts an acknowledge interaction with no input schema', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'Proceed', mode: InteractionMode::Acknowledge),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('requires a prompt', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step('s', interaction: new Interaction(inputSchema: ['type' => 'object'], mode: InteractionMode::Form))])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.interaction_step');
});

it('requires an input schema for form and redirect modes', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', interaction: new Interaction(prompt: 'P', mode: InteractionMode::Form)),
        Fx::step('b', interaction: new Interaction(prompt: 'P', mode: InteractionMode::Redirect, redirectOperationId: 'redirectOp')),
    ])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(2);
});

it('forbids input schema on acknowledge', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'P', mode: InteractionMode::Acknowledge, inputSchema: ['type' => 'object']),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});

it('rejects redirect interaction without redirectOperationId', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [Fx::step(
        's',
        interaction: new Interaction(prompt: 'P', mode: InteractionMode::Redirect, inputSchema: ['type' => 'object']),
    )])]);
    $ec = new ErrorCollector();
    (new InteractionStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.interaction_step');
});
