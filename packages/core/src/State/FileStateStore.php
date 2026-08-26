<?php

declare(strict_types=1);

namespace Alama\Arazzo\State;

use Alama\Arazzo\Contracts\StateStoreInterface;
use RuntimeException;

/**
 * Filesystem-backed state store for CLI and single-process runs.
 *
 * One JSON document per execution at {dir}/{executionId}.json. TTL is
 * accepted for interface parity but intentionally ignored: a resumed CLI
 * run must find its state even days later.
 */
final class FileStateStore implements StateStoreInterface
{
    private readonly string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (getcwd() ?: '.').'/storage/executions';

        if (!is_dir($this->dir) && !@mkdir($this->dir, 0777, true) && !is_dir($this->dir)) {
            throw new RuntimeException("Could not create state directory: {$this->dir}");
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $bytesWritten = file_put_contents($this->path($executionId), $json);

        if ($bytesWritten === false) {
            throw new RuntimeException("Could not persist execution state: {$executionId}");
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array
    {
        $path = $this->path($executionId);

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        // JSON objects decode to string-keyed maps; skip anything else.
        if (array_keys($decoded) !== array_filter(array_keys($decoded), 'is_string')) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function delete(string $executionId): void
    {
        $path = $this->path($executionId);

        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException("Could not delete execution state: {$executionId}");
        }
    }

    public function path(string $executionId): string
    {
        return $this->dir.'/'.$this->sanitize($executionId).'.json';
    }

    private function sanitize(string $executionId): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $executionId) ?? 'execution';
    }
}
