<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

class JsonPointer
{
    public static function resolve(array $data, ?string $pointer): mixed
    {
        if ($pointer === null || $pointer === '') {
            return $data;
        }

        $segments = explode('/', ltrim($pointer, '/'));

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
