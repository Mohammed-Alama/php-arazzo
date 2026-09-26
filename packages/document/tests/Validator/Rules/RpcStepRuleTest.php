<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\RpcStepRule;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts qualified rpcMethod with matching protocol and content type', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [
            Fx::step('g', rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet', rpcProtocol: RpcProtocol::Grpc),
        ])],
        sources: [new SourceDescription('proto', '/svc.proto', SourceType::Protobuf)],
    );
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('rejects malformed rpcMethod references', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [
        Fx::step('a', rpcMethod: 'GetPet', rpcProtocol: RpcProtocol::Grpc),
        Fx::step('b', rpcMethod: '$sourceDescriptions.missing.pkg/M', rpcProtocol: RpcProtocol::Grpc),
    ])]);
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(2);
});

it('rejects cross-protocol request content types', function (): void {
    $document = Fx::doc(
        workflows: [Fx::wf('w', [
            Fx::step('g',
                rpcMethod: '$sourceDescriptions.proto.com.acme.PetService/GetPet',
                rpcProtocol: RpcProtocol::Twirp,
                body: new RequestBody('application/grpc', null, []),
            ),
        ])],
        sources: [new SourceDescription('proto', '/svc.proto', SourceType::Protobuf)],
    );
    $ec = new ErrorCollector();
    (new RpcStepRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
    expect($ec->errors()[0]->code)->toBe('step.rpc_step');
});
