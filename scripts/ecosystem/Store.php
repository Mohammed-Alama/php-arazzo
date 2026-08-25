<?php

declare(strict_types=1);

namespace Ecosystem;

final class Store
{
    public function __construct(
        private readonly string $feedPath,
        private readonly string $snapshotsDir,
        private readonly int $cap = 2000,
    ) {
    }

    /**
     * @param FeedEvent[] $events
     *
     * @return array{written:int,feedCount:int,feedPath:string}
     */
    public function commit(array $events, bool $dryRun = false): array
    {
        $existing = $this->loadExisting();

        $byId = [];
        foreach ($existing as $row) {
            $byId[$row['id']] = $row;
        }

        $new = 0;
        foreach ($events as $ev) {
            if (!isset($byId[$ev->id])) {
                $byId[$ev->id] = $ev->toArray();
                $new++;
            }

            if (!$dryRun) {
                $date = substr($ev->publishedAt, 0, 10) ?: gmdate('Y-m-d');
                $dir = $this->snapshotsDir . '/' . $date;
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ev->source) . '--' . substr($ev->id, 0, 12) . '.json';
                file_put_contents($dir . '/' . $safe, json_encode($ev->toArray() + ['raw' => $ev->raw], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        $all = array_values($byId);
        usort($all, fn ($a, $b) => strcmp($b['publishedAt'] ?? '', $a['publishedAt'] ?? ''));
        $all = array_slice($all, 0, $this->cap);

        // prune snapshots older than 30d on commit (also done in workflow for safety)
        if (!$dryRun && is_dir($this->snapshotsDir)) {
            $cut = strtotime('-30 days');
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->snapshotsDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getMTime() < $cut) {
                    @unlink($file->getPathname());
                }
            }
            foreach (glob($this->snapshotsDir . '/*') as $d) {
                if (is_dir($d) && count(glob($d . '/*') ?: []) === 0) {
                    @rmdir($d);
                }
            }
        }

        if (!$dryRun) {
            $dir = dirname($this->feedPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($this->feedPath, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            // also docs/generated mirror
            $mirror = dirname(__DIR__, 2) . '/docs/generated/ecosystem-feed.json';
            if (!is_dir(dirname($mirror))) {
                mkdir(dirname($mirror), 0777, true);
            }
            file_put_contents($mirror, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }

        return ['written' => $new, 'feedCount' => count($all), 'feedPath' => $this->feedPath];
    }

    /** @return array<int,array<string,mixed>> */
    private function loadExisting(): array
    {
        if (!is_file($this->feedPath)) {
            return [];
        }
        $raw = file_get_contents($this->feedPath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
