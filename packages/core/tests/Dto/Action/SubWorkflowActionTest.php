<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Spec\Enum\ActionKind;

it('constructs a success invoke action', function () {
    $a = new SubWorkflowSuccessAction(
        name: 'reconcile',
        workflowId: 'ride-reconcile',
        parameters: ['rideId' => new Expression('{$steps.book.outputs.rideId}')],
        criteria: [],
    );

    expect($a->name)->toBe('reconcile')
        ->and($a->kind)->toBe(ActionKind::Invoke)
        ->and($a->workflowId)->toBe('ride-reconcile')
        ->and($a->parameters)->toHaveKey('rideId')
        ->and($a->criteria)->toBe([]);
});

it('constructs a failure invoke action', function () {
    $a = new SubWorkflowFailureAction(
        name: 'refund',
        workflowId: 'refund-workflow',
        parameters: [],
        criteria: [],
    );

    expect($a->kind)->toBe(ActionKind::Invoke)
        ->and($a->workflowId)->toBe('refund-workflow');
});
