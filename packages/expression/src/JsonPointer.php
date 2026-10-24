<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

/**
 * JSON Pointer resolver (RFC 6901 §5, JSON string form); consumed by the
 * ExpressionEngine facade and exposed through its public jsonPointer() method.
 *
 * Runtime expressions carry the pointer after `#`, so the URI-fragment
 * percent-decoding rules of RFC 6901 §6 do not apply: `%` stays literal.
 */
class JsonPointer
{
    /**
     * A pointer is valid when it is empty or starts with '/'. Escape-sequence
     * validity is enforced separately by the document validator's
     * expression.jsonpointer_syntax rule.
     */
    public static function isValid(string $pointer): bool
    {
        return $pointer === '' || $pointer[0] === '/';
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function resolve(array $data, ?string $pointer): mixed
    {
        if ($pointer === null || $pointer === '') {
            return $data;
        }

        if (!self::isValid($pointer)) {
            return null;
        }

        // Exactly one leading slash is the reference-token separator; a second
        // one introduces the empty-string key.
        $segments = explode('/', substr($pointer, 1));

        $current = $data;
        foreach ($segments as $segment) {
            $key = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return null;
            }
        }

        return $current;
    }
}
