<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Evaluation;

class JsonPointer
{
    /**
     * @param  array<array-key, mixed>  $data
     */
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
