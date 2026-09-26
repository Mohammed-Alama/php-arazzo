<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;

final class Fx
{
    /**
     * @param  list<Workflow>  $workflows
     * @param  list<SourceDescription>  $sources
     * @param  array<string,mixed>  $extensions
     * @param  array<string,mixed>|null  $rawRoot
     */
    public static function doc(
        array $workflows = [],
        array $sources = [],
        array $extensions = [],
        ?array $rawRoot = null,
        ?Components $components = null,
    ): ArazzoDocument {
        return new ArazzoDocument(
            '1.0.0',
            new Info('T', null, null, '1'),
            $sources,
            $workflows,
            $components ?? new Components([], [], [], []),
            $extensions,
            $rawRoot,
        );
    }

    /**
     * @param  list<Parameter>  $params
     * @param  list<SuccessCriterion>  $crit
     * @param  list<mixed>  $onSuccess
     * @param  list<mixed>  $onFailure
     * @param  array<string,Expression>  $outputs
     */
    public static function step(
        string $id = 's',
        ?string $opId = 'op',
        ?string $opPath = null,
        ?string $wfId = null,
        array $params = [],
        ?RequestBody $body = null,
        array $crit = [],
        array $onSuccess = [],
        array $onFailure = [],
        array $outputs = [],
        ?string $operationName = null,
        ?string $rpcMethod = null,
        ?RpcProtocol $rpcProtocol = null,
        ?GraphQlOperation $graphqlOperation = null,
        ?Interaction $interaction = null,
    ): Step {
        $flow = new StepFlow(onSuccess: $onSuccess, onFailure: $onFailure);
        $io = new StepIo(parameters: $params, requestBody: $body, successCriteria: $crit, outputs: $outputs);

        // Determine target based on provided fields
        $hasHttp = $opId !== null || $opPath !== null;
        $hasWorkflow = $wfId !== null;
        $hasWsdl = $operationName !== null;
        $hasRpc = $rpcMethod !== null;
        $hasGraphql = $graphqlOperation !== null;
        $hasInteraction = $interaction !== null;

        $targetCount = (int) $hasHttp + (int) $hasWorkflow + (int) $hasWsdl + (int) $hasRpc + (int) $hasGraphql + (int) $hasInteraction;

        if ($targetCount > 1) {
            // Multiple targets provided; let validation rules catch it. We'll construct a StepTarget with all fields.
            $target = new StepTarget(
                operationId: $opId,
                operationPath: $opPath,
                workflowId: $wfId,
                operationName: $operationName,
                rpcMethod: $rpcMethod,
                rpcProtocol: $rpcProtocol,
                graphqlOperation: $graphqlOperation,
                interaction: $interaction,
            );
        } elseif ($hasHttp) {
            return StepFactory::http($id, null, $flow, $io, $opId, $opPath);
        } elseif ($hasWorkflow) {
            return StepFactory::workflow($id, null, $flow, $io, $wfId);
        } elseif ($hasWsdl) {
            return StepFactory::wsdl($id, null, $flow, $io, $operationName);
        } elseif ($hasRpc) {
            return StepFactory::rpc($id, null, $flow, $io, $rpcMethod, $rpcProtocol);
        } elseif ($hasGraphql) {
            return StepFactory::graphql($id, null, $flow, $io, $graphqlOperation);
        } elseif ($hasInteraction) {
            return StepFactory::interaction($id, null, $flow, $io, $interaction);
        } else {
            $target = new StepTarget();
        }

        $flow = new StepFlow(onSuccess: $onSuccess, onFailure: $onFailure);
        $io = new StepIo(parameters: $params, requestBody: $body, successCriteria: $crit, outputs: $outputs);

        return new Step($id, null, $target, $flow, $io);
    }

    /**
     * @param  list<Step>  $steps
     * @param  list<string>  $dep
     * @param  array<string,mixed>|null  $inputs
     * @param  array<string,Expression>  $outputs
     * @param  list<Parameter>  $parameters
     */
    public static function wf(
        string $id,
        array $steps,
        array $dep = [],
        ?array $inputs = null,
        array $outputs = [],
        array $parameters = [],
    ): Workflow {
        return new Workflow($id, null, null, $inputs, $dep, $steps, [], [], $outputs, $parameters);
    }
}
