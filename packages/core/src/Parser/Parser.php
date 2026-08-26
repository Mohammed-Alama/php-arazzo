<?php

declare(strict_types=1);

namespace Alama\Arazzo\Parser;

use Alama\Arazzo\Parser\Exceptions\ParserException;
use Alama\Arazzo\Spec\Action\FailureAction;
use Alama\Arazzo\Spec\Action\FailureEndAction;
use Alama\Arazzo\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Spec\Action\RetryAction;
use Alama\Arazzo\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Spec\Action\SuccessAction;
use Alama\Arazzo\Spec\Action\SuccessEndAction;
use Alama\Arazzo\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\CriterionType;
use Alama\Arazzo\Spec\Enum\ExpressionType;
use Alama\Arazzo\Spec\Enum\ParameterIn;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\PayloadReplacement;
use Alama\Arazzo\Spec\RawDocument;
use Alama\Arazzo\Spec\RequestBody;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;
use Alama\Arazzo\Spec\Workflow;
use InvalidArgumentException;

class Parser
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
            return is_string($k) && str_starts_with($k, 'x-');
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

    /** @param array<string,mixed> $arr */
    protected function requireString(array $arr, string $key, ParseContext $ctx): string
    {
        if (!array_key_exists($key, $arr)) {
            throw ParserException::missingField($ctx, $key);
        }
        $v = $arr[$key];
        if (!is_string($v)) {
            throw ParserException::wrongType($ctx->push($key), 'string', $v);
        }

        return $v;
    }

    /** @param array<string,mixed> $arr */
    protected function optionalString(array $arr, string $key, ParseContext $ctx): ?string
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }
        $v = $arr[$key];
        if (!is_string($v)) {
            throw ParserException::wrongType($ctx->push($key), 'string', $v);
        }

        return $v;
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

    /** @return array<string,mixed> */
    protected function requireObjectMap(mixed $node, ParseContext $ctx): array
    {
        if (!is_array($node) || (array_is_list($node) && $node !== [])) {
            throw ParserException::wrongType($ctx, 'object', $node);
        }

        /** @var array<string,mixed> $node */
        return $node;
    }

    /** @return list<mixed> */
    protected function requireList(mixed $node, ParseContext $ctx): array
    {
        if (!is_array($node) || (!empty($node) && !array_is_list($node))) {
            throw ParserException::wrongType($ctx, 'list', $node);
        }

        return $node;
    }

    protected function parseSourceDescription(mixed $node, ParseContext $ctx): SourceDescription
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $enum = SourceType::tryFrom($type)
            ?? throw ParserException::invalidEnum(
                $ctx->push('type'), 'openapi|arazzo', $type,
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
            foreach (array_values($d) as $i => $item) {
                if (!is_string($item)) {
                    throw ParserException::wrongType(
                        $ctx->push('dependsOn')->push($i), 'string', $item,
                    );
                }
                $dependsOn[] = $item;
            }
        }

        $steps = [];
        if (!array_key_exists('steps', $obj)) {
            throw ParserException::missingField($ctx, 'steps');
        }
        $rawSteps = $this->requireList($obj['steps'], $ctx->push('steps'));
        foreach (array_values($rawSteps) as $i => $item) {
            $steps[] = $this->parseStep($item, $ctx->push('steps')->push($i));
        }

        $successActions = [];
        if (($s = $this->optionalList($obj, 'successActions', $ctx)) !== null) {
            foreach (array_values($s) as $i => $item) {
                $successActions[] = $this->parseSuccessAction($item, $ctx->push('successActions')->push($i));
            }
        }

        $failureActions = [];
        if (($f = $this->optionalList($obj, 'failureActions', $ctx)) !== null) {
            foreach (array_values($f) as $i => $item) {
                $failureActions[] = $this->parseFailureAction($item, $ctx->push('failureActions')->push($i));
            }
        }

        $parameters = [];
        if (($p = $this->optionalList($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

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
     * @param  array<string,mixed>  $arr
     * @return array<int|string,mixed>|null
     */
    protected function optionalArray(array $arr, string $key, ParseContext $ctx): ?array
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }
        $v = $arr[$key];
        if (!is_array($v)) {
            throw ParserException::wrongType($ctx->push($key), 'array', $v);
        }

        return $v;
    }

    /**
     * @param  array<string,mixed>  $arr
     * @return list<mixed>|null
     */
    protected function optionalList(array $arr, string $key, ParseContext $ctx): ?array
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }

        return $this->requireList($arr[$key], $ctx->push($key));
    }

    protected function parseStep(mixed $node, ParseContext $ctx): Step
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $parameters = [];
        if (($p = $this->optionalList($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $requestBody = null;
        if (array_key_exists('requestBody', $obj) && $obj['requestBody'] !== null) {
            $requestBody = $this->parseRequestBody($obj['requestBody'], $ctx->push('requestBody'));
        }

        $criteria = [];
        if (($c = $this->optionalList($obj, 'successCriteria', $ctx)) !== null) {
            foreach (array_values($c) as $i => $item) {
                $criteria[] = $this->parseSuccessCriterion($item, $ctx->push('successCriteria')->push($i));
            }
        }

        $onSuccess = [];
        if (($o = $this->optionalList($obj, 'onSuccess', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onSuccess[] = $this->parseSuccessAction($item, $ctx->push('onSuccess')->push($i));
            }
        }

        $onFailure = [];
        if (($o = $this->optionalList($obj, 'onFailure', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onFailure[] = $this->parseFailureAction($item, $ctx->push('onFailure')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        $action = $this->optionalString($obj, 'action', $ctx);
        $channelPath = $this->optionalString($obj, 'channelPath', $ctx);
        $correlationIdRaw = $this->optionalString($obj, 'correlationId', $ctx);
        $correlationId = $correlationIdRaw !== null ? new Expression($correlationIdRaw) : null;
        $strictValidation = $this->optionalBool($obj, 'x-strict-validation', $ctx);
        $idempotencyKey = $this->optionalBool($obj, 'x-idempotency-key', $ctx);
        $idempotencyHeader = $this->optionalString($obj, 'x-idempotency-header', $ctx);

        $dependsOn = [];
        if (($d = $this->optionalList($obj, 'dependsOn', $ctx)) !== null) {
            foreach (array_values($d) as $i => $item) {
                if (!is_string($item)) {
                    throw ParserException::wrongType(
                        $ctx->push('dependsOn')->push((string) $i), 'string', $item,
                    );
                }
                $dependsOn[] = $item;
            }
        }

        return new Step(
            stepId: $this->requireString($obj, 'stepId', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            operationId: $this->optionalString($obj, 'operationId', $ctx),
            operationPath: $this->optionalString($obj, 'operationPath', $ctx),
            workflowId: $this->optionalString($obj, 'workflowId', $ctx),
            parameters: $parameters,
            requestBody: $requestBody,
            successCriteria: $criteria,
            onSuccess: $onSuccess,
            onFailure: $onFailure,
            outputs: $outputs,
            dependsOn: $dependsOn,
            action: $action,
            channelPath: $channelPath,
            correlationId: $correlationId,
            strictValidation: $strictValidation,
            idempotencyKey: $idempotencyKey,
            idempotencyHeader: $idempotencyHeader,
            timeout: $this->optionalInt($obj, 'timeout', $ctx),
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
    private function parseValueOrSelector(mixed $value, ParseContext $ctx, bool $forceStringExpression = false): mixed
    {
        if (is_string($value)) {
            if ($forceStringExpression || preg_match('/^\{\$.+\}$/', $value) === 1) {
                return new Expression($value);
            }

            return $value;
        }

        if (is_array($value) && array_key_exists('selector', $value) && array_key_exists('type', $value)) {
            $rawType = $value['type'];
            $version = null;

            if (is_array($rawType)) {
                $typeCtx = $ctx->push('type');
                $typeStr = $this->requireString($rawType, 'type', $typeCtx);
                $version = $this->optionalString($rawType, 'version', $typeCtx);
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

        if (is_array($value)) {
            $parsed = [];
            foreach ($value as $k => $v) {
                $parsed[$k] = $this->parseValueOrSelector($v, $ctx->push((string) $k), $forceStringExpression);
            }

            return $parsed;
        }

        return $value;
    }

    protected function parseRequestBody(mixed $node, ParseContext $ctx): RequestBody
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $replacements = [];
        $rawRepl = $this->optionalList($obj, 'replacements', $ctx);
        if ($rawRepl !== null) {
            foreach (array_values($rawRepl) as $i => $item) {
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
                $t = $this->requireString($rawType, 'type', $typeCtx);
                $version = $this->optionalString($rawType, 'version', $typeCtx);
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
                parameters: $this->parseActionParameters($obj, $ctx, $name),
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
    protected function parseActionParameters(array $obj, ParseContext $ctx, string $actionName): array
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
                parameters: $this->parseActionParameters($obj, $ctx, $name),
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

    /** @param array<string,mixed> $arr */
    protected function optionalNumber(array $arr, string $key, ParseContext $ctx): int|float|null
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }
        $v = $arr[$key];
        if (!is_int($v) && !is_float($v)) {
            throw ParserException::wrongType($ctx->push($key), 'number', $v);
        }

        return $v;
    }

    /** @param array<string,mixed> $arr */
    protected function optionalInt(array $arr, string $key, ParseContext $ctx): ?int
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }
        $v = $arr[$key];
        if (!is_int($v)) {
            throw ParserException::wrongType($ctx->push($key), 'int', $v);
        }

        return $v;
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

    /** @param array<string,mixed> $arr */
    protected function optionalBool(array $arr, string $key, ParseContext $ctx): ?bool
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) {
            return null;
        }
        $v = $arr[$key];
        if (!is_bool($v)) {
            throw ParserException::wrongType($ctx->push($key), 'bool', $v);
        }

        return $v;
    }

    protected function parseComponents(mixed $node, ParseContext $ctx): Components
    {
        if ($node === null) {
            return new Components([], [], [], []);
        }
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = [];
        if (($i = $this->optionalArray($obj, 'inputs', $ctx)) !== null) {
            foreach ($i as $k => $v) {
                if (!is_array($v)) {
                    throw ParserException::wrongType(
                        $ctx->push('inputs')->push((string) $k), 'object (JSON Schema)', $v,
                    );
                }
                /** @var array<string,mixed> $v */
                $inputs[(string) $k] = $v;
            }
        }

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach ($p as $k => $v) {
                $parsed = $this->parseParameter($v, $ctx->push('parameters')->push((string) $k));
                if ($parsed instanceof Reusable) {
                    throw ParserException::wrongType(
                        $ctx->push('parameters')->push((string) $k),
                        'parameter (not a reusable ref)', $v,
                    );
                }
                $parameters[(string) $k] = $parsed;
            }
        }

        $successActions = [];
        if (($s = $this->optionalArray($obj, 'successActions', $ctx)) !== null) {
            foreach ($s as $k => $v) {
                $parsed = $this->parseSuccessAction($v, $ctx->push('successActions')->push((string) $k));
                if ($parsed instanceof Reusable) {
                    throw ParserException::wrongType(
                        $ctx->push('successActions')->push((string) $k),
                        'action (not a reusable ref)', $v,
                    );
                }
                $successActions[(string) $k] = $parsed;
            }
        }

        $failureActions = [];
        if (($f = $this->optionalArray($obj, 'failureActions', $ctx)) !== null) {
            foreach ($f as $k => $v) {
                $parsed = $this->parseFailureAction($v, $ctx->push('failureActions')->push((string) $k));
                if ($parsed instanceof Reusable) {
                    throw ParserException::wrongType(
                        $ctx->push('failureActions')->push((string) $k),
                        'action (not a reusable ref)', $v,
                    );
                }
                $failureActions[(string) $k] = $parsed;
            }
        }

        return new Components($inputs, $parameters, $successActions, $failureActions);
    }

    /**
     * @param  array<string,mixed>  $arr
     * @return array<int|string,mixed>
     */
    protected function requireArray(array $arr, string $key, ParseContext $ctx): array
    {
        if (!array_key_exists($key, $arr)) {
            throw ParserException::missingField($ctx, $key);
        }
        $v = $arr[$key];
        if (!is_array($v)) {
            throw ParserException::wrongType($ctx->push($key), 'array', $v);
        }

        return $v;
    }
}
