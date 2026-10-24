<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Parser;

use Alama\Arazzo\Contracts\Spec\Action\FailureAction;
use Alama\Arazzo\Contracts\Spec\Action\FailureEndAction;
use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\RpcProtocol;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\GraphQlOperation;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\PayloadReplacement;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Contracts\Spec\RequestBody;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Document\Parser\Exceptions\ParserException;
use InvalidArgumentException;

/**
 * Recursive-descent parser for the Arazzo document grammar.
 *
 * PHPMD's TooManyMethods, ExcessiveClassComplexity and CouplingBetweenObjects
 * findings stay above the default thresholds here, deliberately: the class is
 * one pass over one specification, so its methods are the grammar's
 * productions, its complexity is their branch count, and its coupling is the
 * model vocabulary each production constructs. Splitting productions across
 * objects would share the cursor and ParseContext without removing a decision.
 * Field reads and their JSON type checks live in FieldParser, which this class
 * extends. The per-method findings are fixed, not waived (#257).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
class Parser extends FieldParser
{
    public function parse(RawDocument $raw): ArazzoDocument
    {
        $ctx = new ParseContext($raw->path);
        $d = $raw->data;

        $arazzo = $this->requireString($d, 'arazzo', $ctx);

        try {
            $specVersion = SpecVersion::fromRaw($arazzo);
        } catch (InvalidArgumentException) {
            throw ParserException::unsupportedVersion($ctx, $arazzo);
        }

        $self = $this->optionalString($d, '$self', $ctx);

        if (!array_key_exists('info', $d)) {
            throw ParserException::missingField($ctx, 'info');
        }
        $info = $this->parseInfo($d['info'], $ctx->push('info'));

        $sourceDescriptions = [];
        if (array_key_exists('sourceDescriptions', $d) && $d['sourceDescriptions'] !== null) {
            $list = $this->requireList($d['sourceDescriptions'], $ctx->push('sourceDescriptions'));
            foreach ($list as $i => $item) {
                $sourceDescriptions[] = $this->parseSourceDescription($item, $ctx->push('sourceDescriptions')->push($i));
            }
        }

        if (!array_key_exists('workflows', $d)) {
            throw ParserException::missingField($ctx, 'workflows');
        }
        $workflows = [];
        if ($d['workflows'] !== null) {
            $list = $this->requireList($d['workflows'], $ctx->push('workflows'));
            foreach ($list as $i => $item) {
                $workflows[] = $this->parseWorkflow($item, $ctx->push('workflows')->push($i));
            }
        }

        $components = $this->parseComponents($d['components'] ?? null, $ctx->push('components'));

        $extensions = array_filter($d, function ($k) {
            // A decoded root map may hold integer keys (`{"0": …}`).
            return str_starts_with((string) $k, 'x-');
        }, ARRAY_FILTER_USE_KEY);

        return new ArazzoDocument(
            arazzo: $arazzo,
            info: $info,
            sourceDescriptions: $sourceDescriptions,
            workflows: $workflows,
            components: $components,
            specificationExtensions: $extensions,
            rawRoot: $d,
            specVersion: $specVersion,
            self: $self,
        );
    }

    protected function parseInfo(mixed $node, ParseContext $ctx): Info
    {
        $obj = $this->requireObjectMap($node, $ctx);

        return new Info(
            title: $this->requireString($obj, 'title', $ctx),
            summary: $this->optionalString($obj, 'summary', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            version: $this->requireString($obj, 'version', $ctx),
        );
    }

    protected function parseSourceDescription(mixed $node, ParseContext $ctx): SourceDescription
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $enum = SourceType::tryFrom($type)
            ?? throw ParserException::invalidEnum(
                $ctx->push('type'), 'openapi|arazzo|asyncapi|wsdl|protobuf|graphql', $type,
            );

        return new SourceDescription(
            name: $this->requireString($obj, 'name', $ctx),
            url: $this->requireString($obj, 'url', $ctx),
            type: $enum,
        );
    }

    protected function parseWorkflow(mixed $node, ParseContext $ctx): Workflow
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = $this->optionalArray($obj, 'inputs', $ctx);

        $dependsOn = [];
        if (($d = $this->optionalList($obj, 'dependsOn', $ctx)) !== null) {
            foreach ($d as $i => $item) {
                if (!is_string($item)) {
                    throw ParserException::wrongType(
                        $ctx->push('dependsOn')->push($i), 'string', $item,
                    );
                }
                $dependsOn[] = $item;
            }
        }

        if (!array_key_exists('steps', $obj)) {
            throw ParserException::missingField($ctx, 'steps');
        }
        $steps = [];
        foreach ($this->requireList($obj['steps'], $ctx->push('steps')) as $i => $item) {
            $steps[] = $this->parseStep($item, $ctx->push('steps')->push($i));
        }

        $successActions = $this->parseOptionalListOf($obj, 'successActions', $ctx, $this->parseSuccessAction(...));
        $failureActions = $this->parseOptionalListOf($obj, 'failureActions', $ctx, $this->parseFailureAction(...));
        $parameters = $this->parseOptionalListOf($obj, 'parameters', $ctx, $this->parseParameter(...));

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        /** @var array<string,mixed>|null $inputs */
        return new Workflow(
            workflowId: $this->requireString($obj, 'workflowId', $ctx),
            summary: $this->optionalString($obj, 'summary', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            inputs: $inputs,
            dependsOn: $dependsOn,
            steps: $steps,
            successActions: $successActions,
            failureActions: $failureActions,
            outputs: $outputs,
            parameters: $parameters,
        );
    }

    /**
     * Parse an optional list field into a list, preserving the original field
     * order and pointer in any error it raises.
     *
     * @template T
     *
     * @param  array<string,mixed>  $obj
     * @param  callable(mixed, ParseContext): T  $parse
     * @return list<T>
     */
    private function parseOptionalListOf(array $obj, string $key, ParseContext $ctx, callable $parse): array
    {
        $items = $this->optionalList($obj, $key, $ctx);
        if ($items === null) {
            return [];
        }

        $parsed = [];
        foreach ($items as $i => $item) {
            $parsed[] = $parse($item, $ctx->push($key)->push($i));
        }

        return $parsed;
    }

    protected function parseStep(mixed $node, ParseContext $ctx): Step
    {
        $obj = $this->requireObjectMap($node, $ctx);

        return new Step(
            stepId: $this->requireString($obj, 'stepId', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            target: $this->parseStepTarget($obj, $ctx),
            flow: $this->parseStepFlow($obj, $ctx),
            io: $this->parseStepIo($obj, $ctx),
        );
    }

    /**
     * @param  array<string,mixed>  $obj
     */
    protected function parseStepTarget(array $obj, ParseContext $ctx): StepTarget
    {
        $action = $this->optionalString($obj, 'action', $ctx);
        $channelPath = $this->optionalString($obj, 'channelPath', $ctx);
        $correlationIdRaw = $this->optionalString($obj, 'correlationId', $ctx);
        $correlationId = $correlationIdRaw !== null ? new Expression($correlationIdRaw) : null;
        $operationId = $this->optionalString($obj, 'operationId', $ctx);
        $operationPath = $this->optionalString($obj, 'operationPath', $ctx);
        $workflowId = $this->optionalString($obj, 'workflowId', $ctx);
        $operationName = $this->optionalString($obj, 'operationName', $ctx);
        $rpcMethod = $this->optionalString($obj, 'rpcMethod', $ctx);
        $rpcProtocol = $this->parseRpcProtocol($obj, $ctx);
        $graphqlOperation = $this->parseOptionalNode($obj, 'graphqlOperation', $ctx, $this->parseGraphQlOperation(...));
        $interaction = $this->parseOptionalNode($obj, 'interaction', $ctx, $this->parseInteraction(...));

        return match (true) {
            $workflowId !== null => StepTarget::workflow($workflowId),
            $action !== null && $channelPath !== null => StepTarget::async($action, $channelPath, $correlationId),
            $operationId !== null || $operationPath !== null => new StepTarget(
                operationId: $operationId,
                operationPath: $operationPath,
                workflowId: $workflowId,
                action: $action,
                channelPath: $channelPath,
                correlationId: $correlationId,
                operationName: $operationName,
                rpcMethod: $rpcMethod,
                rpcProtocol: $rpcProtocol,
                graphqlOperation: $graphqlOperation,
                interaction: $interaction,
            ),
            default => new StepTarget(
                operationName: $operationName,
                rpcMethod: $rpcMethod,
                rpcProtocol: $rpcProtocol,
                graphqlOperation: $graphqlOperation,
                interaction: $interaction,
            ),
        };
    }

    /** @param array<string,mixed> $obj */
    private function parseRpcProtocol(array $obj, ParseContext $ctx): ?RpcProtocol
    {
        $raw = $this->optionalString($obj, 'rpcProtocol', $ctx);
        if ($raw === null) {
            return null;
        }

        $protocol = RpcProtocol::tryFrom($raw);
        if ($protocol === null) {
            throw ParserException::invalidEnum($ctx->push('rpcProtocol'), 'grpc|grpc-web|twirp|connect', $raw);
        }

        return $protocol;
    }

    /**
     * Parse an optional sub-node through `$parse`, keeping the field's pointer
     * as the context for any error it raises.
     *
     * @template T
     *
     * @param  array<string,mixed>  $obj
     * @param  callable(mixed, ParseContext): T  $parse
     * @return T|null
     */
    private function parseOptionalNode(array $obj, string $key, ParseContext $ctx, callable $parse): mixed
    {
        if (!array_key_exists($key, $obj) || $obj[$key] === null) {
            return null;
        }

        return $parse($obj[$key], $ctx->push($key));
    }

    private function parseGraphQlOperation(mixed $node, ParseContext $ctx): GraphQlOperation
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $schema = $this->requireString($obj, 'schema', $ctx);
        $operation = $this->requireString($obj, 'operation', $ctx);
        $extensions = $this->optionalArray($obj, 'extensions', $ctx);
        $extensionsSelector = null;
        if (array_key_exists('extensionsSelector', $obj) && $obj['extensionsSelector'] !== null) {
            $extensionsSelector = $this->parseSelector($obj['extensionsSelector'], $ctx->push('extensionsSelector'));
        }

        return new GraphQlOperation(
            schema: $schema,
            operation: $operation,
            extensions: $extensions,
            extensionsSelector: $extensionsSelector,
        );
    }

    private function parseInteraction(mixed $node, ParseContext $ctx): Interaction
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $prompt = $this->optionalString($obj, 'prompt', $ctx);
        $timeout = $this->optionalString($obj, 'timeout', $ctx);
        $modeRaw = $this->optionalString($obj, 'mode', $ctx);
        $mode = $modeRaw !== null ? InteractionMode::tryFrom($modeRaw) : null;
        if ($modeRaw !== null && $mode === null) {
            throw ParserException::invalidEnum($ctx->push('mode'), 'form|redirect|acknowledge', $modeRaw);
        }
        $context = $this->optionalArray($obj, 'context', $ctx);
        $inputSchema = $this->optionalArray($obj, 'inputSchema', $ctx);
        $redirect = null;
        if (array_key_exists('redirect', $obj) && $obj['redirect'] !== null) {
            $redirectObj = $this->requireObjectMap($obj['redirect'], $ctx->push('redirect'));
            $redirect = $this->optionalString($redirectObj, 'operationId', $ctx->push('redirect'));
        }

        return new Interaction(
            expectedPayload: $context,
            timeout: $timeout,
            mode: $mode,
            prompt: $prompt,
            redirectOperationId: $redirect,
            inputSchema: $inputSchema,
        );
    }

    /**
     * @param  array<string,mixed>  $obj
     */
    protected function parseStepFlow(array $obj, ParseContext $ctx): StepFlow
    {
        $dependsOn = [];
        if (($d = $this->optionalList($obj, 'dependsOn', $ctx)) !== null) {
            foreach ($d as $i => $item) {
                if (!is_string($item)) {
                    throw ParserException::wrongType(
                        $ctx->push('dependsOn')->push((string) $i), 'string', $item,
                    );
                }
                $dependsOn[] = $item;
            }
        }

        $onSuccess = [];
        if (($o = $this->optionalList($obj, 'onSuccess', $ctx)) !== null) {
            foreach ($o as $i => $item) {
                $onSuccess[] = $this->parseSuccessAction($item, $ctx->push('onSuccess')->push($i));
            }
        }

        $onFailure = [];
        if (($o = $this->optionalList($obj, 'onFailure', $ctx)) !== null) {
            foreach ($o as $i => $item) {
                $onFailure[] = $this->parseFailureAction($item, $ctx->push('onFailure')->push($i));
            }
        }

        return new StepFlow(
            dependsOn: $dependsOn,
            timeout: $this->optionalInt($obj, 'timeout', $ctx),
            onSuccess: $onSuccess,
            onFailure: $onFailure,
            strictValidation: $this->optionalBool($obj, 'x-strict-validation', $ctx),
            idempotencyKey: $this->optionalBool($obj, 'x-idempotency-key', $ctx),
            idempotencyHeader: $this->optionalString($obj, 'x-idempotency-header', $ctx),
        );
    }

    /**
     * @param  array<string,mixed>  $obj
     */
    protected function parseStepIo(array $obj, ParseContext $ctx): StepIo
    {
        $parameters = [];
        if (($p = $this->optionalList($obj, 'parameters', $ctx)) !== null) {
            foreach ($p as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $requestBody = null;
        if (array_key_exists('requestBody', $obj) && $obj['requestBody'] !== null) {
            $requestBody = $this->parseRequestBody($obj['requestBody'], $ctx->push('requestBody'));
        }

        $criteria = [];
        if (($c = $this->optionalList($obj, 'successCriteria', $ctx)) !== null) {
            foreach ($c as $i => $item) {
                $criteria[] = $this->parseSuccessCriterion($item, $ctx->push('successCriteria')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        return new StepIo(
            parameters: $parameters,
            requestBody: $requestBody,
            successCriteria: $criteria,
            outputs: $outputs,
        );
    }

    protected function parseParameter(mixed $node, ParseContext $ctx): Parameter|Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);

        if (array_key_exists('reference', $obj)) {
            return new Reusable(
                reference: $this->requireString($obj, 'reference', $ctx),
                value: $this->normalizeReusableValue($obj['value'] ?? null, $ctx->push('value')),
            );
        }

        $in = null;
        if (($rawIn = $this->optionalString($obj, 'in', $ctx)) !== null) {
            $in = ParameterIn::tryFrom($rawIn)
                ?? throw ParserException::invalidEnum(
                    $ctx->push('in'), 'path|query|header|cookie|body|querystring', $rawIn,
                );
        }
        if (!array_key_exists('value', $obj)) {
            throw ParserException::missingField($ctx, 'value');
        }

        return new Parameter(
            name: $this->requireString($obj, 'name', $ctx),
            in: $in,
            value: $this->parseValueOrSelector($obj['value'], $ctx->push('value'), false),
        );
    }

    /**
     * @return Expression|Selector|scalar|array<mixed>|null
     */
    private function parseValueOrSelector(mixed $value, ParseContext $ctx, bool $forceExpression = false): mixed
    {
        if (is_string($value)) {
            return $forceExpression || preg_match('/^\{\$.+\}$/', $value) === 1
                ? new Expression($value)
                : $value;
        }

        if (is_array($value)) {
            if (array_key_exists('selector', $value) && array_key_exists('type', $value)) {
                return $this->parseSelector($value, $ctx);
            }

            return $this->parseValueMapping($value, $ctx, $forceExpression);
        }

        if ($this->isScalarValue($value)) {
            return $value;
        }

        throw ParserException::wrongType($ctx, 'scalar, map, selector or expression', $value);
    }

    /**
     * @param  array<array-key,mixed>  $value
     * @return array<array-key,mixed>
     */
    private function parseValueMapping(array $value, ParseContext $ctx, bool $forceExpression): array
    {
        $parsed = [];
        foreach ($value as $k => $v) {
            $parsed[$k] = $this->parseValueOrSelector($v, $ctx->push((string) $k), $forceExpression);
        }

        return $parsed;
    }

    /**
     * @phpstan-assert-if-true scalar|null $value
     */
    private function isScalarValue(mixed $value): bool
    {
        return is_bool($value) || is_int($value) || is_float($value) || $value === null;
    }

    private function parseSelector(mixed $node, ParseContext $ctx): Selector
    {
        $value = $this->requireObjectMap($node, $ctx);

        $rawType = $value['type'] ?? null;
        $version = null;

        if (is_array($rawType)) {
            $typeCtx = $ctx->push('type');
            $typeNode = $this->requireObjectMap($rawType, $typeCtx);
            $typeStr = $this->requireString($typeNode, 'type', $typeCtx);
            $version = $this->optionalString($typeNode, 'version', $typeCtx);
        } else {
            $typeStr = $this->requireString($value, 'type', $ctx);
        }

        $type = ExpressionType::tryFrom($typeStr);
        if ($type === null) {
            throw ParserException::invalidEnum($ctx->push('type'), 'simple|regex|jsonpath|xpath', $typeStr);
        }

        return new Selector(
            context: $this->optionalString($value, 'context', $ctx),
            selector: $this->requireString($value, 'selector', $ctx),
            type: $type,
            version: $version ?? $this->optionalString($value, 'version', $ctx),
        );
    }

    protected function parseRequestBody(mixed $node, ParseContext $ctx): RequestBody
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $replacements = [];
        $rawRepl = $this->optionalList($obj, 'replacements', $ctx);
        if ($rawRepl !== null) {
            foreach ($rawRepl as $i => $item) {
                $replacements[] = $this->parsePayloadReplacement($item, $ctx->push('replacements')->push((string) $i));
            }
        }

        return new RequestBody(
            contentType: $this->optionalString($obj, 'contentType', $ctx),
            payload: array_key_exists('payload', $obj)
                ? $this->parseExpressionOrValue($obj['payload'])
                : null,
            replacements: $replacements,
        );
    }

    protected function parsePayloadReplacement(mixed $node, ParseContext $ctx): PayloadReplacement
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (!array_key_exists('value', $obj)) {
            throw ParserException::missingField($ctx, 'value');
        }

        $selectorType = null;
        if (($raw = $this->optionalString($obj, 'targetSelectorType', $ctx)) !== null) {
            $selectorType = in_array($raw, ['jsonpointer', 'jsonpath', 'xpath'], true)
                ? $raw
                : throw ParserException::invalidEnum($ctx->push('targetSelectorType'), 'jsonpointer|jsonpath|xpath', $raw);
        } elseif (($tst = $obj['targetSelectorType'] ?? null) !== null && is_array($tst) && !array_is_list($tst)) {
            // Expression Type Object form {type, version} - kept raw; validated by selector rules.
            /** @var array<string, mixed> $tst */
            $selectorType = $tst;
        }

        return new PayloadReplacement(
            target: $this->requireString($obj, 'target', $ctx),
            value: $this->parseValueOrSelector($obj['value'], $ctx->push('value'), false),
            targetSelectorType: $selectorType,
        );
    }

    protected function parseExpressionOrValue(mixed $node): mixed
    {
        if (is_string($node) && preg_match('/^\{\$.+\}$/', $node) === 1) {
            return new Expression($node);
        }

        return $node;
    }

    protected function parseSuccessCriterion(mixed $node, ParseContext $ctx): SuccessCriterion
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = null;
        $version = null;

        if (array_key_exists('type', $obj) && $obj['type'] !== null) {
            $rawType = $obj['type'];

            if (is_array($rawType)) {
                $typeCtx = $ctx->push('type');
                $typeNode = $this->requireObjectMap($rawType, $typeCtx);
                $t = $this->requireString($typeNode, 'type', $typeCtx);
                $version = $this->optionalString($typeNode, 'version', $typeCtx);
            } else {
                $t = $this->requireString($obj, 'type', $ctx);
            }

            $type = CriterionType::tryFrom($t)
                ?? throw ParserException::invalidEnum(
                    $ctx->push('type'), 'simple|regex|jsonpath|xpath', $t,
                );
        }

        return new SuccessCriterion(
            context: $this->optionalString($obj, 'context', $ctx),
            condition: $this->requireString($obj, 'condition', $ctx),
            type: $type,
            version: $version,
        );
    }

    protected function parseSuccessAction(mixed $node, ParseContext $ctx): SuccessAction|Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (array_key_exists('reference', $obj)) {
            return $this->parseReusable($obj, $ctx);
        }
        $name = $this->requireString($obj, 'name', $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $criteria = $this->parseCriteriaList($obj, $ctx);

        return match ($type) {
            'goto' => new SuccessGotoAction(
                name: $name,
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
                parameters: $this->parseActionParameters($obj, $ctx),
            ),
            'end' => new SuccessEndAction($name, $criteria),
            'invoke' => $this->parseSubWorkflowSuccessAction($name, $obj, $criteria, $ctx),
            default => throw ParserException::invalidActionType($ctx->push('type'), $type),
        };
    }

    /**
     * 1.1 Action Object `parameters` (goto actions): list of Parameter Objects
     * or Reusables. Spec requires workflowId on the action when present.
     *
     * @return list<Parameter|Reusable>
     */
    /**
     * @param  array<string,mixed>  $obj
     * @return list<Parameter|Reusable>
     */
    protected function parseActionParameters(array $obj, ParseContext $ctx): array
    {
        if (!isset($obj['parameters']) || !is_array($obj['parameters'])) {
            return [];
        }

        if (!isset($obj['workflowId'])) {
            throw ParserException::missingField($ctx->push('parameters')->push('workflowId'), 'workflowId');
        }

        $params = [];
        foreach (array_values($obj['parameters']) as $i => $item) {
            $params[] = $this->parseParameter($item, $ctx->push('parameters')->push((string) $i));
        }

        return $params;
    }

    protected function parseReusable(mixed $node, ParseContext $ctx): Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);

        return new Reusable(
            reference: $this->requireString($obj, 'reference', $ctx),
            value: $this->normalizeReusableValue($obj['value'] ?? null, $ctx->push('value')),
        );
    }

    /**
     * @return Expression|Selector|scalar|array<mixed>|null
     */
    protected function normalizeReusableValue(mixed $value, ParseContext $ctx): mixed
    {
        if ($value === null || is_scalar($value) || is_array($value)) {
            return $value;
        }

        throw ParserException::wrongType($ctx, 'scalar, array, expression or selector', $value);
    }

    /**
     * @param  array<string,mixed>  $obj
     * @return list<SuccessCriterion>
     */
    private function parseCriteriaList(array $obj, ParseContext $ctx): array
    {
        $list = $this->optionalList($obj, 'criteria', $ctx);
        if ($list === null) {
            return [];
        }
        $out = [];
        foreach ($list as $i => $item) {
            $out[] = $this->parseSuccessCriterion($item, $ctx->push('criteria')->push($i));
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $d
     * @param  list<SuccessCriterion>  $criteria
     */
    private function parseSubWorkflowSuccessAction(string $name, array $d, array $criteria, ParseContext $ctx): SubWorkflowSuccessAction
    {
        $workflowId = $this->requireString($d, 'workflowId', $ctx);
        $version = $this->optionalString($d, 'version', $ctx);
        $parameters = [];
        if (array_key_exists('parameters', $d) && $d['parameters'] !== null) {
            $paramsMap = $this->requireObjectMap($d['parameters'], $ctx->push('parameters'));
            foreach ($paramsMap as $k => $v) {
                $parameters[(string) $k] = $this->parseValueOrSelector($v, $ctx->push('parameters')->push((string) $k), true);
            }
        }

        return new SubWorkflowSuccessAction($name, $workflowId, $parameters, $criteria, $version);
    }

    protected function parseFailureAction(mixed $node, ParseContext $ctx): FailureAction|Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (array_key_exists('reference', $obj)) {
            return $this->parseReusable($obj, $ctx);
        }
        $name = $this->requireString($obj, 'name', $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $criteria = $this->parseCriteriaList($obj, $ctx);

        return match ($type) {
            'goto' => new FailureGotoAction(
                name: $name,
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
                parameters: $this->parseActionParameters($obj, $ctx),
            ),
            'end' => new FailureEndAction($name, $criteria),
            'retry' => new RetryAction(
                name: $name,
                retryAfter: $this->optionalNumber($obj, 'retryAfter', $ctx),
                retryLimit: $this->optionalInt($obj, 'retryLimit', $ctx),
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
            ),
            'invoke' => $this->parseSubWorkflowFailureAction($name, $obj, $criteria, $ctx),
            default => throw ParserException::invalidActionType($ctx->push('type'), $type),
        };
    }

    /**
     * @param  array<string,mixed>  $d
     * @param  list<SuccessCriterion>  $criteria
     */
    private function parseSubWorkflowFailureAction(string $name, array $d, array $criteria, ParseContext $ctx): SubWorkflowFailureAction
    {
        $workflowId = $this->requireString($d, 'workflowId', $ctx);
        $version = $this->optionalString($d, 'version', $ctx);
        $parameters = [];
        if (array_key_exists('parameters', $d) && $d['parameters'] !== null) {
            $paramsMap = $this->requireObjectMap($d['parameters'], $ctx->push('parameters'));
            foreach ($paramsMap as $k => $v) {
                $parameters[(string) $k] = $this->parseValueOrSelector($v, $ctx->push('parameters')->push((string) $k), true);
            }
        }

        return new SubWorkflowFailureAction($name, $workflowId, $parameters, $criteria, $version);
    }

    /** @return array<string,Expression|Selector|scalar|array<mixed>|null> */
    protected function parseOutputsMap(mixed $node, ParseContext $ctx): array
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $out = [];
        foreach ($obj as $k => $v) {
            $out[$k] = $this->parseValueOrSelector($v, $ctx->push((string) $k), true);
        }

        return $out;
    }

    protected function parseComponents(mixed $node, ParseContext $ctx): Components
    {
        if ($node === null) {
            return new Components([], [], [], []);
        }
        $obj = $this->requireObjectMap($node, $ctx);

        return new Components(
            inputs: $this->parseComponentInputs($obj, $ctx),
            parameters: $this->parseComponentParameters($obj, $ctx),
            successActions: $this->parseComponentActions($obj, 'successActions', $ctx, $this->parseSuccessAction(...)),
            failureActions: $this->parseComponentActions($obj, 'failureActions', $ctx, $this->parseFailureAction(...)),
            interactions: $this->parseComponentInteractions($obj, $ctx),
        );
    }

    /**
     * @param  array<string,mixed>  $obj
     * @return array<string,array<string,mixed>>
     */
    private function parseComponentInputs(array $obj, ParseContext $ctx): array
    {
        $items = $this->optionalArray($obj, 'inputs', $ctx);
        if ($items === null) {
            return [];
        }

        $inputs = [];
        foreach ($items as $k => $v) {
            if (!is_array($v)) {
                throw ParserException::wrongType(
                    $ctx->push('inputs')->push((string) $k), 'object (JSON Schema)', $v,
                );
            }
            /** @var array<string,mixed> $v */
            $inputs[(string) $k] = $v;
        }

        return $inputs;
    }

    /**
     * @param  array<string,mixed>  $obj
     * @return array<string,Parameter>
     */
    private function parseComponentParameters(array $obj, ParseContext $ctx): array
    {
        $items = $this->optionalArray($obj, 'parameters', $ctx);
        if ($items === null) {
            return [];
        }

        $parameters = [];
        foreach ($items as $k => $v) {
            $parsed = $this->parseParameter($v, $ctx->push('parameters')->push((string) $k));
            if ($parsed instanceof Reusable) {
                throw ParserException::wrongType(
                    $ctx->push('parameters')->push((string) $k),
                    'parameter (not a reusable ref)', $v,
                );
            }
            $parameters[(string) $k] = $parsed;
        }

        return $parameters;
    }

    /**
     * @template T of SuccessAction|FailureAction
     *
     * @param  array<string,mixed>  $obj
     * @param  callable(mixed, ParseContext): (T|Reusable)  $parse
     * @return array<string,T>
     */
    private function parseComponentActions(array $obj, string $key, ParseContext $ctx, callable $parse): array
    {
        $items = $this->optionalArray($obj, $key, $ctx);
        if ($items === null) {
            return [];
        }

        $parsed = [];
        foreach ($items as $k => $v) {
            $action = $parse($v, $ctx->push($key)->push((string) $k));
            if ($action instanceof Reusable) {
                throw ParserException::wrongType(
                    $ctx->push($key)->push((string) $k),
                    'action (not a reusable ref)', $v,
                );
            }
            $parsed[(string) $k] = $action;
        }

        return $parsed;
    }

    /**
     * @param  array<string,mixed>  $obj
     * @return array<string,Interaction>
     */
    private function parseComponentInteractions(array $obj, ParseContext $ctx): array
    {
        $items = $this->optionalArray($obj, 'interactions', $ctx);
        if ($items === null) {
            return [];
        }

        $interactions = [];
        foreach ($items as $k => $v) {
            if (!is_array($v)) {
                throw ParserException::wrongType(
                    $ctx->push('interactions')->push((string) $k), 'object', $v,
                );
            }
            $interactions[(string) $k] = $this->parseInteraction($v, $ctx->push('interactions')->push((string) $k));
        }

        return $interactions;
    }
}
