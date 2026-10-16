<?php

declare(strict_types=1);

namespace Ecosystem;

final class GhCli
{
    public static function isAvailable(): bool
    {
        $out = @shell_exec('gh --version 2>&1');

        return is_string($out) && str_contains($out, 'gh version');
    }

    /**
     * Call `gh api <endpoint>` and return decoded JSON or null on failure.
     * Endpoint should be like `repos/OAI/Arazzo-Specification/releases?per_page=2`
     * without leading slash or host. Supports --paginate for full collection.
     *
     * @return array<mixed>|null
     */
    public static function api(string $endpoint, bool $paginate = false, bool $verbose = false): ?array
    {
        // Escape endpoint for shell - wrap in single quotes and escape single quotes
        $endpoint = trim($endpoint, '/');
        $escaped = str_replace("'", "'\"'\"'", $endpoint);
        $cmd = "gh api '{$escaped}'";
        if ($paginate) {
            $cmd .= ' --paginate';
        }
        // gh api outputs JSON to stdout; errors to stderr
        $cmd .= ' 2>&1';

        if ($verbose) {
            fwrite(STDERR, "  gh: {$cmd}\n");
        }

        $output = @shell_exec($cmd);
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        // gh may emit warnings before JSON (e.g., "gh: ..."). Try to extract JSON part.
        $jsonStart = strpos($output, '[');
        $objStart = strpos($output, '{');
        if ($jsonStart === false && $objStart === false) {
            // Not JSON - maybe error message
            if ($verbose) {
                fwrite(STDERR, '  gh output not JSON: '.substr($output, 0, 200)."\n");
            }

            return null;
        }
        // Find earliest JSON start
        $start = null;
        if ($jsonStart !== false && $objStart !== false) {
            $start = min($jsonStart, $objStart);
        } elseif ($jsonStart !== false) {
            $start = $jsonStart;
        } else {
            $start = $objStart;
        }

        $jsonStr = substr($output, $start);
        // Trim trailing non-JSON (gh sometimes appends newline warnings after)
        $decoded = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try whole output
            $decoded = json_decode(trim($output), true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($verbose) {
                fwrite(STDERR, '  gh json decode failed: '.json_last_error_msg()."\n");
            }

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Wrapper that prefers gh api, falls back to curl if gh unavailable or fails.
     * Used for single-entity endpoints that gh handles well.
     */
    public static function apiOrNull(string $endpoint, bool $paginate = false, bool $verbose = false): ?array
    {
        if (!self::isAvailable()) {
            if ($verbose) {
                fwrite(STDERR, "  gh not available, skipping gh api\n");
            }

            return null;
        }

        return self::api($endpoint, $paginate, $verbose);
    }

    /**
     * Run a GraphQL query via `gh api graphql`. Variables are passed with `-F`
     * so numeric values (e.g. `first`) keep their type.
     *
     * @param  array<string,string|int>  $variables
     * @return array<string,mixed>|null the decoded `{data:…}` response, or null on failure
     */
    public static function graphql(string $query, array $variables = [], bool $verbose = false): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $cmd = 'gh api graphql -f query='.self::shellQuote($query);
        foreach ($variables as $key => $value) {
            $cmd .= ' -F '.$key.'='.self::shellQuote((string) $value);
        }
        $cmd .= ' 2>&1';

        if ($verbose) {
            fwrite(STDERR, "  gh: {$cmd}\n");
        }

        $output = @shell_exec($cmd);
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        $decoded = json_decode(trim($output), true);
        if (!is_array($decoded)) {
            $start = strpos($output, '{');
            if ($start !== false) {
                $decoded = json_decode(substr($output, $start), true);
            }
        }
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Remaining API budget (A9). The `rate_limit` endpoint itself does not count
     * against the limit, so this is safe to call on every run.
     *
     * @return array<string,mixed>|null decoded `resources` map from `gh api rate_limit`
     */
    public static function rateLimit(bool $verbose = false): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $output = @shell_exec('gh api rate_limit 2>&1');
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        $decoded = json_decode(trim($output), true);
        if (!is_array($decoded)) {
            return null;
        }

        return is_array($decoded['resources'] ?? null) ? $decoded['resources'] : $decoded;
    }

    private static function shellQuote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
