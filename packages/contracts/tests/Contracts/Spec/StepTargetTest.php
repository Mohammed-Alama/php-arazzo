<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\StepTarget;

it('builds an http target from an operationId', function (): void {
    $target = StepTarget::http(operationId: 'getToken');

    expect($target->operationId)->toBe('getToken')
        ->and($target->operationPath)->toBeNull();
});

it('builds an http target from an operationPath', function (): void {
    $target = StepTarget::http(operationPath: '/pets');

    expect($target->operationPath)->toBe('/pets')
        ->and($target->operationId)->toBeNull();
});

it('throws when the http target declares no operation', function (): void {
    StepTarget::http();
})->throws(InvalidArgumentException::class);

it('throws when the http target declares both operations', function (): void {
    StepTarget::http(operationId: 'getToken', operationPath: '/pets');
})->throws(InvalidArgumentException::class);

it('builds a workflow target', function (): void {
    $target = StepTarget::workflow('checkout');

    expect($target->workflowId)->toBe('checkout');
});

it('builds an async target with action, channel and correlation', function (): void {
    $correlation = new Expression('{$request.body#/correlationId}');
    $target = StepTarget::async('receive', 'channels/rides/created', $correlation);

    expect($target->action)->toBe('receive')
        ->and($target->channelPath)->toBe('channels/rides/created')
        ->and($target->correlationId)->toBe($correlation);
});

it('builds a wsdl target', function (): void {
    $target = StepTarget::wsdl('GetToken');

    expect($target->operationName)->toBe('GetToken');
});

it('builds an rpc target with its wire protocol', function (): void {
    $target = StepTarget::rpc('GetToken', RpcProtocol::Grpc);

    expect($target->rpcMethod)->toBe('GetToken')
        ->and($target->rpcProtocol)->toBe(RpcProtocol::Grpc);
});

it('builds a graphql target', function (): void {
    $target = StepTarget::graphql('query GetToken');

    expect($target->graphqlOperation)->toBe('query GetToken');
});

it('builds an interaction target', function (): void {
    $interaction = new Interaction(expectedPayload: 'approve');
    $target = StepTarget::interaction($interaction);

    expect($target->interaction)->toBe($interaction);
});
