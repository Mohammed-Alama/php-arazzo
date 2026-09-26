<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Rules\StepOperationTargetPresentRule;
use Alama\Arazzo\Tests\Support\Fx;

it('accepts each of the six targets individually', function (): void {
    $flow = new StepFlow();
    $io = new StepIo();

    $doc = Fx::wf('w', [
        StepFactory::http('a', null, $flow, $io, 'op'),
        StepFactory::http('b', null, $flow, $io, operationPath: '#/paths/pets/get'),
        StepFactory::workflow('c', null, $flow, $io, 'other'),
        StepFactory::wsdl('d', null, $flow, $io, 'GetPet'),
        StepFactory::rpc('e', null, $flow, $io, '$sourceDescriptions.proto.M/Get', RpcProtocol::Grpc),
        StepFactory::graphql('f', null, $flow, $io, new GraphQlOperation(schema: '$sourceDescriptions.gql', operation: 'query Q')),
        StepFactory::interaction('g', null, $flow, $io, new Interaction(prompt: 'Go')),
    ]);
    $document = Fx::doc(workflows: [$doc]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toBeEmpty();
});

it('flags steps that set an http target alongside a protocol target', function (): void {
    $flow = new StepFlow();
    $io = new StepIo();

    $doc = Fx::wf('w', [
        StepFactory::wsdl('a', null, $flow, $io, 'GetPet'), // wsdl + http? Actually wsdl sets operationName, not http; need a step with both http (opId) and wsdl? We'll make step with both operationId and operationName using custom StepTarget.
        // We'll construct a step with both operationId and operationName manually.
        new Step('b', null, new StepTarget(operationId: 'op', operationName: 'GetPet'), $flow, $io),
        new Step('c', null, new StepTarget(workflowId: 'other', interaction: new Interaction(prompt: 'Go')), $flow, $io),
    ]);
    $document = Fx::doc(workflows: [$doc]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(2);
    expect($ec->errors()[0]->code)->toBe('step.operation_target_present');
});

it('still flags steps with no target at all', function (): void {
    $document = Fx::doc(workflows: [Fx::wf('w', [new Step('ghost', null, new StepTarget(), new StepFlow(), new StepIo())])]);
    $ec = new ErrorCollector();
    (new StepOperationTargetPresentRule())->check($document, SymbolTable::build($document), $ec);

    expect($ec->errors())->toHaveCount(1);
});
