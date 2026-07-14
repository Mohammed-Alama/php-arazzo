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
}
