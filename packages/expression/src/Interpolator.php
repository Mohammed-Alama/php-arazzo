<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;

/**
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
final class Interpolator
{
    private const EXPRESSION_PATTERN = '/\{\$([^}]+)\}|\$\{([^}]+)\}/';

    /**
     * @return list<string>
     */
    public function extract(string $template): array
    {
        $expressions = [];
        preg_match_all(self::EXPRESSION_PATTERN, $template, $fullMatches, PREG_SET_ORDER);

        if (empty($fullMatches)) {
            return [];
        }

        foreach ($fullMatches as $match) {
            $inner = $this->extractInner($match);
            if ($inner === null) {
                continue;
            }

            $trimmed = trim($inner);
            if ($trimmed === '') {
                continue;
            }

            $bare = str_starts_with($trimmed, '$') ? $trimmed : '$'.$trimmed;
            $expressions[] = $bare;
        }

        return $expressions;
    }

    /**
     * @param  array{0: string, 1?: string, 2?: string}  $match
     */
    private function extractInner(array $match): ?string
    {
        if (!empty($match[1])) {
            return $match[1];
        }
        if (!empty($match[2])) {
            return $match[2];
        }

        return null;
    }

    /**
     * @param  callable(string): mixed  $resolver
     * @param  array{stringify?: (callable(mixed): string)|null}  $options
     */
    public function interpolate(string $template, callable $resolver, array $options = []): string
    {
        $opts = new InterpolationOptions(
            stringify: $options['stringify'] ?? null,
        );

        return $this->interpolateWithOptions($template, $resolver, $opts);
    }

    public function interpolateWithOptions(string $template, callable $resolver, ?InterpolationOptions $options = null): string
    {
        $opts = $options ?? new InterpolationOptions();
        $stringifyOpt = $opts->getStringify();

        $result = preg_replace_callback(
            self::EXPRESSION_PATTERN,
            function (array $match) use ($resolver, $stringifyOpt): string {
                return $this->processMatch($match, $resolver, $stringifyOpt);
            },
            $template,
        );

        return $result ?? $template;
    }

    /**
     * @param  array{0: string, 1?: string, 2?: string}  $match
     */
    private function processMatch(array $match, callable $resolver, ?callable $stringifyOpt): string
    {
        $inner = $this->extractInner($match);

        if ($inner === null) {
            throw new ExpressionSyntaxException('Malformed embedded expression');
        }

        $trimmed = trim($inner);
        if ($trimmed === '') {
            throw new ExpressionSyntaxException('Malformed embedded expression');
        }

        $bare = str_starts_with($trimmed, '$') ? $trimmed : '$'.$trimmed;

        $resolved = $resolver($bare);

        if ($stringifyOpt !== null) {
            return self::stringify(call_user_func($stringifyOpt, $resolved));
        }

        return self::stringify($resolved);
    }

    /**
     * Total stringification: scalars stringify natively, structured values as
     * JSON, everything else (null, and resource handles the resolver cannot
     * produce) as empty. A custom stringifier that returns a non-string is
     * normalised the same way rather than being cast blindly.
     */
    private static function stringify(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value);

            return $encoded === false ? '' : $encoded;
        }

        return '';
    }

    public function test(string $raw): bool
    {
        try {
            (new Parser())->parse($raw);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
