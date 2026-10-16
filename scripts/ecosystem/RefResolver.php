<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Resolves a `roadmapRefs` key to a real target (A8).
 *
 * Key space (§5.6):
 *  - `plan:<stem>[#<anchor>]`  → a `docs/superpowers/plans/*<stem>*.md` doc (optional heading)
 *  - `spec:<stem>[#<anchor>]`  → a `docs/superpowers/specs/*<stem>*.md` doc (optional heading)
 *  - `upstream:<owner>/<repo>#<n>` → a tracked upstream GitHub item; validated for form here and,
 *    when `config/ecosystem/watchlist.json` exists, required to be a listed watchlist item.
 *
 * Anything else fails. Used by `scripts/ecosystem/check-refs.php` and the A8 test.
 */
final class RefResolver
{
    public function __construct(private readonly string $root) {}

    /**
     * @return array{ok:bool,ref:string,kind:?string,target:?string,error:?string}
     */
    public function resolve(string $ref): array
    {
        if (preg_match('~^(plan|spec):(.+?)(?:#(.+))?$~', $ref, $m) === 1) {
            return $this->resolveDoc($ref, $m[1], $m[2], $m[3] ?? null);
        }

        if (preg_match('~^upstream:([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)#([0-9]+)$~', $ref) === 1) {
            return $this->resolveUpstream($ref);
        }

        return $this->fail($ref, 'unknown ref scheme (expected plan:/spec:/upstream:)');
    }

    /**
     * @return array{ok:bool,ref:string,kind:?string,target:?string,error:?string}
     */
    private function resolveDoc(string $ref, string $kind, string $stem, ?string $anchor): array
    {
        $dir = $kind === 'plan' ? 'docs/superpowers/plans' : 'docs/superpowers/specs';
        $file = $this->findByStem($dir, $stem);
        if ($file === null) {
            return $this->fail($ref, "no {$kind} doc matching stem `{$stem}` under {$dir}");
        }

        if ($anchor !== null && $anchor !== '' && !$this->hasHeading($file, $anchor)) {
            return $this->fail($ref, "no heading `#{$anchor}` in {$file}");
        }

        return ['ok' => true, 'ref' => $ref, 'kind' => $kind, 'target' => $file, 'error' => null];
    }

    /**
     * @return array{ok:bool,ref:string,kind:?string,target:?string,error:?string}
     */
    private function resolveUpstream(string $ref): array
    {
        $watchlist = $this->root.'/config/ecosystem/watchlist.json';
        if (is_file($watchlist)) {
            $data = json_decode((string) file_get_contents($watchlist), true);
            $items = is_array($data) ? ($data['items'] ?? $data) : [];
            $target = substr($ref, strlen('upstream:'));
            $found = false;
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (is_array($item) && ($item['ref'] ?? null) === $target) {
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                return $this->fail($ref, 'not listed in config/ecosystem/watchlist.json');
            }
        }

        return ['ok' => true, 'ref' => $ref, 'kind' => 'upstream', 'target' => $ref, 'error' => null];
    }

    private function findByStem(string $dir, string $stem): ?string
    {
        $dirPath = $this->root.'/'.$dir;
        if (!is_dir($dirPath)) {
            return null;
        }

        $matches = [];
        foreach ((array) glob($dirPath.'/*.md') as $file) {
            $base = basename($file, '.md');
            if ($base === $stem || str_ends_with($base, '-'.$stem) || str_contains($base, $stem)) {
                $matches[] = $file;
            }
        }
        if ($matches === []) {
            return null;
        }

        // Most specific (shortest) match wins; deterministic.
        usort($matches, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));

        return $matches[0];
    }

    private function hasHeading(string $file, string $anchor): bool
    {
        $contents = (string) file_get_contents($file);
        foreach (explode("\n", $contents) as $line) {
            if (preg_match('/^#{1,6}\s+(.+?)\s*$/', $line, $m) === 1 && $this->slug($m[1]) === $anchor) {
                return true;
            }
        }

        return false;
    }

    private function slug(string $heading): string
    {
        $slug = strtolower(trim($heading));
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug) ?? $slug;
        $slug = preg_replace('/\s+/', '-', $slug) ?? $slug;

        return $slug;
    }

    /**
     * @return array{ok:bool,ref:string,kind:?string,target:?string,error:?string}
     */
    private function fail(string $ref, string $error): array
    {
        return ['ok' => false, 'ref' => $ref, 'kind' => null, 'target' => null, 'error' => $error];
    }
}
