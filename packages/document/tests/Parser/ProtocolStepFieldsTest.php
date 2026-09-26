<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Parser;

use Alama\Arazzo\Contracts\Spec\Enum\Format;
use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Parser;

$decode = function (string $yaml): RawDocument {
    return new RawDocument((new SymfonyYamlDecoder())->decode($yaml), 'memory://protocol', Format::Yaml);
};

it('parses wsdl-step operationName', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: soap, type: wsdl, url: ./service.wsdl }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            operationName: GetPet
    YAML));

    expect($document->workflows[0]->steps[0]->target->operationName)->toBe('GetPet');
    expect($document->workflows[0]->steps[0]->target->operationId)->toBeNull();
});

it('parses rpc-step rpcMethod and rpcProtocol', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: proto, type: protobuf, url: ./svc.proto }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            rpcMethod: $sourceDescriptions.proto.com.acme.PetService/GetPet
            rpcProtocol: grpc
    YAML));

    $step = $document->workflows[0]->steps[0];
    expect($step->target->rpcMethod)->toBe('$sourceDescriptions.proto.com.acme.PetService/GetPet');
    expect($step->target->rpcProtocol)->toBe(RpcProtocol::Grpc);
});

it('parses graphql-step graphqlOperation object', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions:
      - { name: gql, type: graphql, url: ./schema.graphql }
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            graphqlOperation:
              schema: $sourceDescriptions.gql
              operation: "query GetToken { token }"
              extensions:
                contentType: application/json
    YAML));

    $op = $document->workflows[0]->steps[0]->target->graphqlOperation;
    expect($op)->toBeInstanceOf(GraphQlOperation::class);
    expect($op->schema)->toBe('$sourceDescriptions.gql');
    expect($op->operation)->toBe('query GetToken { token }');
    expect($op->extensions)->toBe(['contentType' => 'application/json']);
});

it('parses interaction-step interaction and components.interactions', function () use ($decode): void {
    $document = (new Parser())->parse($decode(<<<'YAML'
    arazzo: 1.2.0
    info: { title: "T", version: "1" }
    sourceDescriptions: []
    components:
      interactions:
        confirmation:
          prompt: Confirm
    workflows:
      - workflowId: w
        steps:
          - stepId: s
            interaction:
              prompt: Confirm transfer
              context: { account: "1234" }
              inputSchema: { type: object }
              mode: redirect
              redirect:
                operationId: confirm
    YAML));

    $step = $document->workflows[0]->steps[0];
    expect($step->target->interaction)->not->toBeNull();
    expect($step->target->interaction->prompt)->toBe('Confirm transfer');
    expect($step->target->interaction->mode)->toBe(InteractionMode::Redirect);
    expect($step->target->interaction->redirectOperationId)->toBe('confirm');
    expect($step->target->interaction->inputSchema)->toBe(['type' => 'object']);
    expect($document->components->interactions)->toHaveKey('confirmation');
    expect($document->components->interactions['confirmation']->prompt)->toBe('Confirm');
});
