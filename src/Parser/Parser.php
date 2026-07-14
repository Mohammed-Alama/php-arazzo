<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Parser;

use Alama\LaravelArazzo\Exceptions\ParserException;

class Parser
{
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

    /** @param array<string,mixed> $arr */
    protected function optionalInt(array $arr, string $key, ParseContext $ctx): ?int
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) return null;
        $v = $arr[$key];
        if (!is_int($v)) throw ParserException::wrongType($ctx->push($key), 'int', $v);
        return $v;
    }

    /** @param array<string,mixed> $arr */
    protected function optionalBool(array $arr, string $key, ParseContext $ctx): ?bool
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) return null;
        $v = $arr[$key];
        if (!is_bool($v)) throw ParserException::wrongType($ctx->push($key), 'bool', $v);
        return $v;
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<int|string,mixed>
     */
    protected function requireArray(array $arr, string $key, ParseContext $ctx): array
    {
        if (!array_key_exists($key, $arr)) throw ParserException::missingField($ctx, $key);
        $v = $arr[$key];
        if (!is_array($v)) throw ParserException::wrongType($ctx->push($key), 'array', $v);
        return $v;
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<int|string,mixed>|null
     */
    protected function optionalArray(array $arr, string $key, ParseContext $ctx): ?array
    {
        if (!array_key_exists($key, $arr) || $arr[$key] === null) return null;
        $v = $arr[$key];
        if (!is_array($v)) throw ParserException::wrongType($ctx->push($key), 'array', $v);
        return $v;
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
        if (!is_array($node) || !array_is_list($node)) {
            throw ParserException::wrongType($ctx, 'list', $node);
        }
        return $node;
    }
    protected function parseInfo(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Info
    {
        $obj = $this->requireObjectMap($node, $ctx);
        return new \Alama\LaravelArazzo\Dto\Info(
            title:       $this->requireString($obj, 'title', $ctx),
            summary:     $this->optionalString($obj, 'summary', $ctx),
            description: $this->optionalString($obj, 'description', $ctx),
            version:     $this->requireString($obj, 'version', $ctx),
        );
    }

    protected function parseSourceDescription(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\SourceDescription
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $enum = \Alama\LaravelArazzo\Dto\Enum\SourceType::tryFrom($type)
            ?? throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidEnum(
                $ctx->push('type'), 'openapi|arazzo', $type,
            );
        return new \Alama\LaravelArazzo\Dto\SourceDescription(
            name: $this->requireString($obj, 'name', $ctx),
            url:  $this->requireString($obj, 'url', $ctx),
            type: $enum,
        );
    }

    protected function parseParameter(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Parameter
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $in = null;
        if (($rawIn = $this->optionalString($obj, 'in', $ctx)) !== null) {
            $in = \Alama\LaravelArazzo\Dto\Enum\ParameterIn::tryFrom($rawIn)
                ?? throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidEnum(
                    $ctx->push('in'), 'path|query|header|cookie|body', $rawIn,
                );
        }
        if (!array_key_exists('value', $obj)) {
            throw \Alama\LaravelArazzo\Exceptions\ParserException::missingField($ctx, 'value');
        }
        return new \Alama\LaravelArazzo\Dto\Parameter(
            name:  $this->requireString($obj, 'name', $ctx),
            in:    $in,
            value: $this->parseExpressionOrValue($obj['value']),
        );
    }

    protected function parsePayloadReplacement(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\PayloadReplacement
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (!array_key_exists('value', $obj)) {
            throw \Alama\LaravelArazzo\Exceptions\ParserException::missingField($ctx, 'value');
        }
        return new \Alama\LaravelArazzo\Dto\PayloadReplacement(
            target: $this->requireString($obj, 'target', $ctx),
            value:  $this->parseExpressionOrValue($obj['value']),
        );
    }

    protected function parseRequestBody(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\RequestBody
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $replacements = [];
        $rawRepl = $this->optionalArray($obj, 'replacements', $ctx);
        if ($rawRepl !== null) {
            foreach (array_values($rawRepl) as $i => $item) {
                $replacements[] = $this->parsePayloadReplacement($item, $ctx->push('replacements')->push((string)$i));
            }
        }
        return new \Alama\LaravelArazzo\Dto\RequestBody(
            contentType:  $this->optionalString($obj, 'contentType', $ctx),
            payload:      array_key_exists('payload', $obj)
                ? $this->parseExpressionOrValue($obj['payload'])
                : null,
            replacements: $replacements,
        );
    }

    protected function parseSuccessCriterion(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\SuccessCriterion
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $type = null;
        if (($t = $this->optionalString($obj, 'type', $ctx)) !== null) {
            $type = \Alama\LaravelArazzo\Dto\Enum\CriterionType::tryFrom($t)
                ?? throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidEnum(
                    $ctx->push('type'), 'simple|regex|jsonpath|xpath', $t,
                );
        }
        return new \Alama\LaravelArazzo\Dto\SuccessCriterion(
            context:   $this->optionalString($obj, 'context', $ctx),
            condition: $this->requireString($obj, 'condition', $ctx),
            type:      $type,
        );
    }

    protected function parseReusable(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);
        return new \Alama\LaravelArazzo\Dto\Reusable(
            reference: $this->requireString($obj, 'reference', $ctx),
            value:     $obj['value'] ?? null,
        );
    }

    protected function parseExpressionOrValue(mixed $node): mixed
    {
        if (is_string($node) && preg_match('/^\{\$.+\}$/', $node) === 1) {
            return new \Alama\LaravelArazzo\Dto\Expression($node);
        }
        return $node;
    }

    protected function parseSuccessAction(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Action\SuccessAction|\Alama\LaravelArazzo\Dto\Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (array_key_exists('reference', $obj)) {
            return $this->parseReusable($obj, $ctx);
        }
        $name = $this->requireString($obj, 'name', $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $criteria = $this->parseCriteriaList($obj, $ctx);

        return match ($type) {
            'goto' => new \Alama\LaravelArazzo\Dto\Action\SuccessGotoAction(
                name: $name,
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
            ),
            'end' => new \Alama\LaravelArazzo\Dto\Action\SuccessEndAction($name, $criteria),
            default => throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidActionType($ctx->push('type'), $type),
        };
    }

    protected function parseFailureAction(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Action\FailureAction|\Alama\LaravelArazzo\Dto\Reusable
    {
        $obj = $this->requireObjectMap($node, $ctx);
        if (array_key_exists('reference', $obj)) {
            return $this->parseReusable($obj, $ctx);
        }
        $name = $this->requireString($obj, 'name', $ctx);
        $type = $this->requireString($obj, 'type', $ctx);
        $criteria = $this->parseCriteriaList($obj, $ctx);

        return match ($type) {
            'goto' => new \Alama\LaravelArazzo\Dto\Action\FailureGotoAction(
                name: $name,
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
            ),
            'end' => new \Alama\LaravelArazzo\Dto\Action\FailureEndAction($name, $criteria),
            'retry' => new \Alama\LaravelArazzo\Dto\Action\RetryAction(
                name: $name,
                retryAfter: $this->optionalInt($obj, 'retryAfter', $ctx),
                retryLimit: $this->optionalInt($obj, 'retryLimit', $ctx),
                stepId: $this->optionalString($obj, 'stepId', $ctx),
                workflowId: $this->optionalString($obj, 'workflowId', $ctx),
                criteria: $criteria,
            ),
            default => throw \Alama\LaravelArazzo\Exceptions\ParserException::invalidActionType($ctx->push('type'), $type),
        };
    }

    /**
     * @param array<string,mixed> $obj
     * @return list<\Alama\LaravelArazzo\Dto\SuccessCriterion>
     */
    private function parseCriteriaList(array $obj, ParseContext $ctx): array
    {
        $list = $this->optionalArray($obj, 'criteria', $ctx);
        if ($list === null) return [];
        $out = [];
        foreach (array_values($list) as $i => $item) {
            $out[] = $this->parseSuccessCriterion($item, $ctx->push('criteria')->push($i));
        }
        return $out;
    }

    /** @return array<string,\Alama\LaravelArazzo\Dto\Expression> */
    protected function parseOutputsMap(mixed $node, ParseContext $ctx): array
    {
        $obj = $this->requireObjectMap($node, $ctx);
        $out = [];
        foreach ($obj as $k => $v) {
            if (!is_string($v)) {
                throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType($ctx->push((string) $k), 'string (expression)', $v);
            }
            $out[$k] = new \Alama\LaravelArazzo\Dto\Expression($v);
        }
        return $out;
    }
    protected function parseStep(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Step
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $requestBody = null;
        if (array_key_exists('requestBody', $obj) && $obj['requestBody'] !== null) {
            $requestBody = $this->parseRequestBody($obj['requestBody'], $ctx->push('requestBody'));
        }

        $criteria = [];
        if (($c = $this->optionalArray($obj, 'successCriteria', $ctx)) !== null) {
            foreach (array_values($c) as $i => $item) {
                $criteria[] = $this->parseSuccessCriterion($item, $ctx->push('successCriteria')->push($i));
            }
        }

        $onSuccess = [];
        if (($o = $this->optionalArray($obj, 'onSuccess', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onSuccess[] = $this->parseSuccessAction($item, $ctx->push('onSuccess')->push($i));
            }
        }

        $onFailure = [];
        if (($o = $this->optionalArray($obj, 'onFailure', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onFailure[] = $this->parseFailureAction($item, $ctx->push('onFailure')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        return new \Alama\LaravelArazzo\Dto\Step(
            stepId:          $this->requireString($obj, 'stepId', $ctx),
            description:     $this->optionalString($obj, 'description', $ctx),
            operationId:     $this->optionalString($obj, 'operationId', $ctx),
            operationPath:   $this->optionalString($obj, 'operationPath', $ctx),
            workflowId:      $this->optionalString($obj, 'workflowId', $ctx),
            parameters:      $parameters,
            requestBody:     $requestBody,
            successCriteria: $criteria,
            onSuccess:       $onSuccess,
            onFailure:       $onFailure,
            outputs:         $outputs,
        );
    }

    protected function parseWorkflow(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Workflow
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = $this->optionalArray($obj, 'inputs', $ctx);

        $dependsOn = [];
        if (($d = $this->optionalArray($obj, 'dependsOn', $ctx)) !== null) {
            foreach (array_values($d) as $i => $item) {
                if (!is_string($item)) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('dependsOn')->push($i), 'string', $item,
                    );
                }
                $dependsOn[] = $item;
            }
        }

        $steps = [];
        $rawSteps = $this->requireArray($obj, 'steps', $ctx);
        foreach (array_values($rawSteps) as $i => $item) {
            $steps[] = $this->parseStep($item, $ctx->push('steps')->push($i));
        }

        $successActions = [];
        if (($s = $this->optionalArray($obj, 'successActions', $ctx)) !== null) {
            foreach (array_values($s) as $i => $item) {
                $successActions[] = $this->parseSuccessAction($item, $ctx->push('successActions')->push($i));
            }
        }

        $failureActions = [];
        if (($f = $this->optionalArray($obj, 'failureActions', $ctx)) !== null) {
            foreach (array_values($f) as $i => $item) {
                $failureActions[] = $this->parseFailureAction($item, $ctx->push('failureActions')->push($i));
            }
        }

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        /** @var array<string,mixed>|null $inputs */
        return new \Alama\LaravelArazzo\Dto\Workflow(
            workflowId:     $this->requireString($obj, 'workflowId', $ctx),
            summary:        $this->optionalString($obj, 'summary', $ctx),
            description:    $this->optionalString($obj, 'description', $ctx),
            inputs:         $inputs,
            dependsOn:      $dependsOn,
            steps:          $steps,
            successActions: $successActions,
            failureActions: $failureActions,
            outputs:        $outputs,
            parameters:     $parameters,
        );
    }

    protected function parseComponents(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Components
    {
        if ($node === null) {
            return new \Alama\LaravelArazzo\Dto\Components([], [], [], []);
        }
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = [];
        if (($i = $this->optionalArray($obj, 'inputs', $ctx)) !== null) {
            foreach ($i as $k => $v) {
                if (!is_array($v)) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
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
                $parameters[(string) $k] = $this->parseParameter($v, $ctx->push('parameters')->push((string) $k));
            }
        }

        $successActions = [];
        if (($s = $this->optionalArray($obj, 'successActions', $ctx)) !== null) {
            foreach ($s as $k => $v) {
                $parsed = $this->parseSuccessAction($v, $ctx->push('successActions')->push((string) $k));
                if ($parsed instanceof \Alama\LaravelArazzo\Dto\Reusable) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
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
                if ($parsed instanceof \Alama\LaravelArazzo\Dto\Reusable) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('failureActions')->push((string) $k),
                        'action (not a reusable ref)', $v,
                    );
                }
                $failureActions[(string) $k] = $parsed;
            }
        }

        return new \Alama\LaravelArazzo\Dto\Components($inputs, $parameters, $successActions, $failureActions);
    }

    public function parse(\Alama\LaravelArazzo\Dto\RawDocument $raw): \Alama\LaravelArazzo\Dto\ArazzoDocument
    {
        $ctx = new ParseContext($raw->path);
        $d = $raw->data;

        $arazzo = $this->requireString($d, 'arazzo', $ctx);

        if (!array_key_exists('info', $d)) {
            throw \Alama\LaravelArazzo\Exceptions\ParserException::missingField($ctx, 'info');
        }
        $info = $this->parseInfo($d['info'], $ctx->push('info'));

        $sourceDescriptions = [];
        if (array_key_exists('sourceDescriptions', $d) && $d['sourceDescriptions'] !== null) {
            $list = $this->requireList($d['sourceDescriptions'], $ctx->push('sourceDescriptions'));
            foreach ($list as $i => $item) {
                $sourceDescriptions[] = $this->parseSourceDescription($item, $ctx->push('sourceDescriptions')->push($i));
            }
        }

        $workflows = [];
        if (array_key_exists('workflows', $d) && $d['workflows'] !== null) {
            $list = $this->requireList($d['workflows'], $ctx->push('workflows'));
            foreach ($list as $i => $item) {
                $workflows[] = $this->parseWorkflow($item, $ctx->push('workflows')->push($i));
            }
        }

        $components = $this->parseComponents($d['components'] ?? null, $ctx->push('components'));

        $extensions = [];
        foreach ($d as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'x-')) {
                $extensions[$k] = $v;
            }
        }

        return new \Alama\LaravelArazzo\Dto\ArazzoDocument(
            arazzo:                  $arazzo,
            info:                    $info,
            sourceDescriptions:      $sourceDescriptions,
            workflows:               $workflows,
            components:              $components,
            specificationExtensions: $extensions,
        );
    }
}
