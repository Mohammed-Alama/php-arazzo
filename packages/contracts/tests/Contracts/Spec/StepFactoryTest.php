<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;

it('builds an http step', function (): void {
    $step = StepFactory::http('get-token', null, new StepFlow(), new StepIo(), operationId: 'GetToken');

    expect($step->stepId)->toBe('get-token')
        ->and($step->target->operationId)->toBe('GetToken');
});

it('builds a workflow step', function (): void {
    $step = StepFactory::workflow('invoke', null, new StepFlow(), new StepIo(), 'checkout');

    expect($step->target->workflowId)->toBe('checkout');
});

it('builds an async step with its correlation id', function (): void {
    $correlation = new Expression('{$request.body#/correlationId}');
    $step = StepFactory::async('receive', null, new StepFlow(), new StepIo(), 'receive', 'channels/rides/created', $correlation);

    expect($step->target->action)->toBe('receive')
        ->and($step->target->channelPath)->toBe('channels/rides/created')
        ->and($step->target->correlationId)->toBe($correlation);
});

it('builds an rpc step', function (): void {
    $step = StepFactory::rpc('charge', null, new StepFlow(), new StepIo(), 'Charge', RpcProtocol::Grpc);

    expect($step->target->rpcMethod)->toBe('Charge')
        ->and($step->target->rpcProtocol)->toBe(RpcProtocol::Grpc);
});

it('builds a graphql step', function (): void {
    $step = StepFactory::graphql('load', null, new StepFlow(), new StepIo(), 'query Load');

    expect($step->target->graphqlOperation)->toBe('query Load');
});

it('builds an interaction step', function (): void {
    $interaction = new Interaction(expectedPayload: 'approve');
    $step = StepFactory::interaction('confirm', null, new StepFlow(), new StepIo(), $interaction);

    expect($step->target->interaction)->toBe($interaction);
});

it('builds a wsdl step', function (): void {
    $step = StepFactory::wsdl('get-token', null, new StepFlow(), new StepIo(), 'GetToken');

    expect($step->target->operationName)->toBe('GetToken');
});

it('attaches the given flow and io to the aggregate', function (): void {
    $flow = new StepFlow(dependsOn: ['a'], timeout: 30000, strictValidation: true);
    $io = new StepIo(parameters: [new Parameter('limit', ParameterIn::Query, 25)]);
    $step = StepFactory::http('s', null, $flow, $io, operationPath: '/pets');

    expect($step->flow)->toBe($flow)
        ->and($step->io)->toBe($io)
        ->and($step->target->operationPath)->toBe('/pets');
});
