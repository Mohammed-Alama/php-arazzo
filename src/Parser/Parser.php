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
}
