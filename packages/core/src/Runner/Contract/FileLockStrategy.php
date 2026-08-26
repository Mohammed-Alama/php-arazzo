<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contract;

use RuntimeException;

final class FileLockStrategy implements LockStrategyInterface
{
    private string $lockDir;

    /** @var array<string, resource> */
    private array $heldLocks = [];

    public function __construct(?string $lockDir = null)
    {
        $this->lockDir = $lockDir ?? sys_get_temp_dir().'/arazzo-locks';
        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0777, true);
        }
    }

    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $lockFile = $this->lockDir.'/'.$this->sanitizeKey($key).'.lock';
        $fp = @fopen($lockFile, 'c+');

        if ($fp === false) {
            throw new RuntimeException("Could not create lock file: {$lockFile}");
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new RuntimeException("Could not acquire lock for key: {$key}");
        }

        $this->heldLocks[$key] = $fp;

        try {
            return $callback();
        } finally {
            $this->release($key);
        }
    }

    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        $lockFile = $this->lockDir.'/'.$this->sanitizeKey($key).'.lock';
        $fp = @fopen($lockFile, 'c+');

        if ($fp === false) {
            return false;
        }

        $acquired = flock($fp, LOCK_EX | LOCK_NB);

        if ($acquired) {
            $this->heldLocks[$key] = $fp;

            return true;
        }

        fclose($fp);

        return false;
    }

    public function release(string $key): void
    {
        $fp = $this->heldLocks[$key] ?? null;
        if (is_resource($fp)) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        unset($this->heldLocks[$key]);
    }

    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $key) ?? 'lock';
    }

    public function __destruct()
    {
        foreach (array_keys($this->heldLocks) as $key) {
            $this->release($key);
        }
    }
}
