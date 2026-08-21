<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Runner\Exceptions\UnsupportedSerializationStyleException;

class ParameterSerializer
{
    /**
     * @param array<string, array<string, mixed>> $normalizedParams
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    public static function serialize(string $location, array $normalizedParams, array $payload): array
    {
        $serialized = [];
        foreach ($payload as $name => $value) {
            $def = $normalizedParams[$name] ?? [];
            /** @var string $style */
            $style = $def['style'] ?? self::getDefaultStyle($location);
            /** @var bool $explode */
            $explode = $def['explode'] ?? self::getDefaultExplode($style);

            $serialized[$name] = self::serializeValue($name, $value, $style, $explode, $location);
        }

        return $serialized;
    }

    public static function serializeValue(string $name, mixed $value, string $style, bool $explode, string $location): string
    {
        return match ($style) {
            'simple' => self::serializeSimple($name, $value, $explode),
            'form' => self::serializeForm($name, $value, $explode),
            'matrix' => self::serializeMatrix($name, $value, $explode),
            'label' => self::serializeLabel($name, $value, $explode),
            'spaceDelimited' => self::serializeDelimited($name, $value, $explode, ' '),
            'pipeDelimited' => self::serializeDelimited($name, $value, $explode, '|'),
            default => throw new UnsupportedSerializationStyleException($style, $location),
        };
    }

    private static function asString(mixed $val): string
    {
        if (is_scalar($val)) {
            return (string) $val;
        }

        if ($val instanceof \Stringable) {
            return (string) $val;
        }

        return is_array($val) || is_object($val) ? json_encode($val) ?: '' : '';
    }

    private static function serializeSimple(string $name, mixed $value, bool $explode): string
    {
        if (is_array($value)) {
            // associative vs sequential
            if (array_is_list($value)) {
                $mapped = array_map(fn ($v) => self::asString($v), $value);

                return implode(',', $mapped);
            }
            $parts = [];
            foreach ($value as $k => $v) {
                if ($explode) {
                    $parts[] = self::asString($k) . '=' . self::asString($v);
                } else {
                    $parts[] = self::asString($k) . ',' . self::asString($v);
                }
            }

            return implode(',', $parts);
        }

        return self::asString($value);
    }

    private static function serializeForm(string $name, mixed $value, bool $explode): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                if ($explode) {
                    // ?id=3&id=4&id=5
                    $parts = array_map(fn ($v) => urlencode($name) . '=' . urlencode(self::asString($v)), $value);

                    return implode('&', $parts);
                }

                // ?id=3,4,5
                return urlencode($name) . '=' . implode(',', array_map(fn ($v) => urlencode(self::asString($v)), $value));
            }
            // associative
            if ($explode) {
                $parts = [];
                foreach ($value as $k => $v) {
                    $parts[] = urlencode(self::asString($k)) . '=' . urlencode(self::asString($v));
                }

                return implode('&', $parts);
            }
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = urlencode(self::asString($k)) . ',' . urlencode(self::asString($v));
            }

            return urlencode($name) . '=' . implode(',', $parts);
        }

        return urlencode($name) . '=' . urlencode(self::asString($value));
    }

    private static function serializeMatrix(string $name, mixed $value, bool $explode): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                if ($explode) {
                    $parts = array_map(fn ($v) => ';' . urlencode($name) . '=' . urlencode(self::asString($v)), $value);

                    return implode('', $parts);
                }

                return ';' . urlencode($name) . '=' . implode(',', array_map(fn ($v) => urlencode(self::asString($v)), $value));
            }

            if ($explode) {
                $parts = [];
                foreach ($value as $k => $v) {
                    $parts[] = ';' . urlencode(self::asString($k)) . '=' . urlencode(self::asString($v));
                }

                return implode('', $parts);
            }
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = urlencode(self::asString($k)) . ',' . urlencode(self::asString($v));
            }

            return ';' . urlencode($name) . '=' . implode(',', $parts);
        }

        return ';' . urlencode($name) . '=' . urlencode(self::asString($value));
    }

    private static function serializeLabel(string $name, mixed $value, bool $explode): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '.' . implode($explode ? '.' : ',', array_map(fn ($v) => urlencode(self::asString($v)), $value));
            }
            $parts = [];
            foreach ($value as $k => $v) {
                if ($explode) {
                    $parts[] = urlencode(self::asString($k)) . '=' . urlencode(self::asString($v));
                } else {
                    $parts[] = urlencode(self::asString($k)) . ',' . urlencode(self::asString($v));
                }
            }

            return '.' . implode($explode ? '.' : ',', $parts);
        }

        return '.' . urlencode(self::asString($value));
    }

    private static function serializeDelimited(string $name, mixed $value, bool $explode, string $delimiter): string
    {
        $encodedDelimiter = urlencode($delimiter);
        if (is_array($value)) {
            if ($explode) {
                $parts = array_map(fn ($v) => urlencode($name) . '=' . urlencode(self::asString($v)), $value);

                return implode('&', $parts);
            }

            return urlencode($name) . '=' . implode($encodedDelimiter, array_map(fn ($v) => urlencode(self::asString($v)), $value));
        }

        return urlencode($name) . '=' . urlencode(self::asString($value));
    }

    private static function getDefaultStyle(string $location): string
    {
        return match ($location) {
            'query', 'cookie' => 'form',
            'path', 'header' => 'simple',
            default => 'simple',
        };
    }

    private static function getDefaultExplode(string $style): bool
    {
        return $style === 'form';
    }
}
