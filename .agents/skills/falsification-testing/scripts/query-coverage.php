<?php

declare(strict_types=1);

// Query Pest coverage HTML — human + agent (--json) readable.
// Usage:
//   php query-coverage.php --overview [--package core|laravel|all] [--json]
//   php query-coverage.php --dashboard [--package core] [--json]
//   php query-coverage.php --hotspots [--limit 10] [--package core] [--json]
//   php query-coverage.php --file Runner/Execution/StepExecutor.php [--package core] [--json]
//   php query-coverage.php --uncovered --file Runner/Execution/WorkflowEngine.php [--package core] [--json]
//   php query-coverage.php --help

$root = dirname(__DIR__, 4);
$args = array_slice($argv, 1);

$has = static fn (string $flag): bool => in_array($flag, $args, true);
$get = static function (string $flag) use ($args): ?string {
    $i = array_search($flag, $args, true);
    if ($i === false || !isset($args[$i + 1])) return null;
    $v = $args[$i + 1];
    return str_starts_with($v, '--') ? null : $v;
};

if ($has('--help') || $has('-h') || $args === []) {
    // default to overview if no args? but help if explicit
    if ($args === []) {
        $args = ['--overview'];
    } else {
        fwrite(STDERR, "usage: php query-coverage.php [--overview|--dashboard|--hotspots|--file <path>|--uncovered --file <path>] [--package core|laravel|all] [--limit N] [--json]\n");
        if ($has('--help') || $has('-h')) exit(0);
    }
}

$json = $has('--json');
$package = $get('--package') ?? 'core';
$limit = (int) ($get('--limit') ?? '10');
$overview = $has('--overview');
$dashboard = $has('--dashboard');
$hotspots = $has('--hotspots');
$uncovered = $has('--uncovered');
$fileArg = $get('--file');

// normalize package
$packages = match ($package) {
    'all' => ['core', 'laravel'],
    'laravel' => ['laravel'],
    default => ['core'],
};
if ($fileArg !== null && !$overview && !$hotspots && !$dashboard && !$uncovered) {
    // --file implies file query
}
$wantFile = $fileArg !== null;
$wantHotspots = $hotspots;
$wantDashboard = $dashboard;
$wantOverview = $overview || (!$wantFile && !$wantHotspots && !$wantDashboard && !$uncovered);
if ($uncovered && $fileArg === null) {
    fwrite(STDERR, "error: --uncovered requires --file <path>\n");
    exit(2);
}

// Helpers
function findReport(string $root, string $pkg, string $file): ?string {
    $base = "{$root}/packages/{$pkg}/coverage-report";
    $path = "{$base}/{$file}";
    return file_exists($path) ? $path : null;
}

function parseIndex(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    $xp = new DOMXPath($dom);
    // Total is the first tr after thead where first td is Total
    $total = ['lines_percent' => null, 'lines_covered' => null, 'lines_total' => null, 'func_percent' => null, 'class_percent' => null];
    $directories = [];
    $rows = $xp->query('//tbody/tr');
    foreach ($rows as $tr) {
        $tds = $xp->query('td', $tr);
        if ($tds === false || $tds->length < 10) continue;
        $nameTd = $tds->item(0);
        $name = trim($nameTd?->textContent ?? '');
        // progress bars are in td index 1,4,7
        $extractPercent = static function (DOMNode $td): ?float {
            $bar = null;
            foreach ($td->getElementsByTagName('div') as $div) {
                if ($div->hasAttribute('aria-valuenow')) {
                    $bar = $div;
                    break;
                }
            }
            // also check nested progress-bar
            if ($bar === null) {
                $bars = (new DOMXPath($td->ownerDocument))->query('.//div[@aria-valuenow]', $td);
                if ($bars && $bars->length > 0) $bar = $bars->item(0);
            }
            if ($bar instanceof DOMElement) {
                return (float) $bar->getAttribute('aria-valuenow');
            }
            return null;
        };
        $extractCount = static function (DOMNode $td): array {
            $text = trim($td->textContent);
            $text = html_entity_decode($text, ENT_HTML5);
            // handle &nbsp; / NBSP and various separators
            if (preg_match('/(\d+)\D*\/\D*(\d+)/u', $text, $m)) {
                return [(int)$m[1], (int)$m[2]];
            }
            return [null, null];
        };
        $linesPct = $extractPercent($tds->item(1));
        [$linesCov, $linesTot] = $extractCount($tds->item(3));
        $funcPct = $extractPercent($tds->item(4));
        $classPct = $extractPercent($tds->item(7));
        if (strtolower($name) === 'total') {
            $total = [
                'lines_percent' => $linesPct,
                'lines_covered' => $linesCov,
                'lines_total' => $linesTot,
                'functions_percent' => $funcPct,
                'classes_percent' => $classPct,
            ];
        } else {
            // directory or file link
            $clean = trim(preg_replace('/\s+/', ' ', $name));
            if ($clean !== '') {
                $directories[] = [
                    'name' => $clean,
                    'lines_percent' => $linesPct,
                    'lines_covered' => $linesCov,
                    'lines_total' => $linesTot,
                    'functions_percent' => $funcPct,
                    'classes_percent' => $classPct,
                ];
            }
        }
    }
    return ['total' => $total, 'directories' => $directories];
}

function parseDashboard(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    $xp = new DOMXPath($dom);
    $insufficient = [];
    // Find h3 Insufficient Coverage then table
    $nodes = $xp->query('//h3[contains(text(),"Insufficient Coverage")]/following::table[1]//tr');
    if ($nodes) {
        foreach ($nodes as $tr) {
            $tds = $xp->query('td', $tr);
            if ($tds === false || $tds->length < 2) continue;
            $a = $xp->query('a', $tds->item(0));
            $class = $a && $a->length > 0 ? trim($a->item(0)->textContent) : trim($tds->item(0)->textContent);
            $cov = trim($tds->item(1)->textContent);
            if ($class === '' || $class === 'Class') continue;
            // extract href if present
            $href = $a && $a->length > 0 ? $a->item(0)->getAttribute('href') : null;
            $pct = null;
            if (preg_match('/(\d+(?:\.\d+)?)%/', $cov, $m)) $pct = (float)$m[1];
            $insufficient[] = ['class' => $class, 'coverage' => $pct, 'href' => $href];
        }
    }
    // Also Project Risks but similar
    return $insufficient;
}

function parseFile(string $htmlPath): array {
    $html = file_get_contents($htmlPath);
    if ($html === false) return ['error' => 'cannot read'];
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    $xp = new DOMXPath($dom);
    // File-level summary: reuse parseIndex on file html (first table's Total row is file total)
    $summary = ['lines_percent' => null, 'lines_covered' => null, 'lines_total' => null, 'functions_percent' => null, 'classes_percent' => null];
    $idx = parseIndex($html);
    if (isset($idx['total']['lines_percent'])) {
        $summary = $idx['total'];
    } else {
        $bars = $xp->query('//div[@aria-valuenow]');
        if ($bars && $bars->length > 0) {
            $first = $bars->item(0);
            if ($first instanceof DOMElement) $summary['lines_percent'] = (float)$first->getAttribute('aria-valuenow');
        }
    }
    // Parse uncovered: only executable lines (popin = covered, danger = uncovered). Blank/non-coverable have " d-flex".
    $uncovered = [];
    $executable = 0;
    $all = $xp->query('//a[starts-with(@id,"")]');
    if ($all) {
        foreach ($all as $a) {
            if (!($a instanceof DOMElement)) continue;
            $id = $a->getAttribute('id');
            if (!ctype_digit($id)) continue;
            $tr = $a->parentNode?->parentNode;
            if (!($tr instanceof DOMElement)) continue;
            $class = $tr->getAttribute('class');
            $isCovered = str_contains($class, 'covered-by');
            $isDanger = str_contains($class, 'danger');
            $isPopin = str_contains($class, 'popin');
            // executable iff popin (covered) or danger (uncovered executable)
            if (!($isPopin || $isDanger)) continue; // not coverable (e.g. <?php, use, blank)
            $executable++;
            if ($isDanger && !$isCovered) {
                $uncovered[] = (int)$id;
            }
        }
    }
    $uncovered = array_values(array_unique($uncovered));
    sort($uncovered);
    // fallback if summary still missing
    if ($summary['lines_total'] === null) $summary['lines_total'] = $executable;
    if ($summary['lines_covered'] === null) $summary['lines_covered'] = $executable - count($uncovered);
    if ($summary['lines_percent'] === null && $executable > 0) $summary['lines_percent'] = round(($summary['lines_covered'] / max(1, $summary['lines_total'])) * 100, 2);
    return ['summary' => $summary, 'uncovered' => $uncovered, 'executable' => $executable];
}

$output = [];
$hasError = false;

foreach ($packages as $pkg) {
    $indexPath = "{$root}/packages/{$pkg}/coverage-report/index.html";
    $dashPath = "{$root}/packages/{$pkg}/coverage-report/dashboard.html";
    if (!file_exists($indexPath)) {
        $output[$pkg] = ['error' => "report not found at packages/{$pkg}/coverage-report/index.html — run make coverage"];
        $hasError = true;
        continue;
    }
    $indexHtml = file_get_contents($indexPath);
    $parsedIndex = parseIndex((string)$indexHtml);
    $dashHtml = file_exists($dashPath) ? file_get_contents($dashPath) : null;
    $insufficient = $dashHtml !== false && $dashHtml !== null ? parseDashboard((string)$dashHtml) : [];

    $pkgOut = [
        'package' => $pkg,
        'report' => "packages/{$pkg}/coverage-report/index.html",
        'total' => $parsedIndex['total'],
        'directories' => $parsedIndex['directories'],
        'hotspots' => array_slice($insufficient, 0, $limit),
        'insufficient' => $insufficient,
    ];

    // File query
    if ($wantFile) {
        $rel = ltrim($fileArg, '/');
        // strip leading packages/*/src/ if present
        $rel = preg_replace('#^packages/(core|laravel)/src/#', '', $rel);
        $rel = preg_replace('#^src/#', '', $rel);
        $htmlFile = "{$root}/packages/{$pkg}/coverage-report/{$rel}.html";
        // Try alternative: if rel already includes directories, try as is
        if (!file_exists($htmlFile)) {
            // try with .php.html suffix already? ensure
            if (!str_ends_with($htmlFile, '.html')) $htmlFile .= '.html';
        }
        // Also try lower-case?
        if (!file_exists($htmlFile)) {
            // Try searching for file by basename
            $found = null;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/packages/{$pkg}/coverage-report"));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), basename($rel) . '.html')) {
                    $found = $f->getPathname();
                    break;
                }
            }
            if ($found) $htmlFile = $found;
        }
        if (!file_exists($htmlFile)) {
            $pkgOut['file'] = ['requested' => $fileArg, 'error' => "per-file report not found — tried {$rel}.html — ensure coverage generated and path is relative to src/ (e.g. Runner/Execution/StepExecutor.php)"];
            $hasError = true;
        } else {
            $fileData = parseFile($htmlFile);
            $pkgOut['file'] = [
                'requested' => $fileArg,
                'report' => str_replace($root . '/', '', $htmlFile),
                'summary' => $fileData['summary'] ?? null,
                'executable_lines' => $fileData['executable'] ?? null,
                'uncovered' => $fileData['uncovered'] ?? [],
                'uncovered_count' => count($fileData['uncovered'] ?? []),
            ];
            if ($uncovered) {
                // detailed uncovered list already in file
            }
        }
    }

    $output[$pkg] = $pkgOut;
}

// Determine single vs multi
$single = count($packages) === 1 ? $output[$packages[0]] : $output;

if ($json) {
    echo json_encode(count($packages) === 1 ? $single : $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($hasError ? 1 : 0);
}

// Human output
foreach ($packages as $pkg) {
    $data = $output[$pkg];
    if (isset($data['error'])) {
        echo "[{$pkg}] ERROR: {$data['error']}\n";
        continue;
    }
    echo "=== {$pkg} ===\n";
    $t = $data['total'];
    if ($t['lines_percent'] !== null) {
        $cov = $t['lines_covered'] !== null ? "{$t['lines_covered']}/{$t['lines_total']}" : "n/a";
        echo "Total: {$t['lines_percent']}% lines ({$cov})";
        if ($t['functions_percent'] !== null) echo " | functions {$t['functions_percent']}%";
        if ($t['classes_percent'] !== null) echo " | classes {$t['classes_percent']}%";
        echo "\n";
    }
    if ($wantOverview) {
        echo "Directories:\n";
        foreach ($data['directories'] as $d) {
            $pct = $d['lines_percent'] !== null ? sprintf("%.2f%%", $d['lines_percent']) : "n/a";
            $cnt = $d['lines_covered'] !== null ? "({$d['lines_covered']}/{$d['lines_total']})" : "";
            $icon = ($d['lines_percent'] !== null && $d['lines_percent'] < 50) ? "🔴" : (($d['lines_percent'] !== null && $d['lines_percent'] < 80) ? "🟡" : "🟢");
            echo "  {$icon} {$pct} {$cnt} {$d['name']}\n";
        }
    }
    if ($wantHotspots || $wantDashboard || $wantOverview) {
        $hots = $data['hotspots'];
        if ($hots !== []) {
            echo "Hotspots (lowest coverage):\n";
            foreach ($hots as $h) {
                $pct = $h['coverage'] !== null ? "{$h['coverage']}%" : "n/a";
                echo "  {$pct} {$h['class']}\n";
            }
        }
    }
    if ($wantFile && isset($data['file'])) {
        $f = $data['file'];
        if (isset($f['error'])) {
            echo "File '{$f['requested']}': ERROR {$f['error']}\n";
        } else {
            $s = $f['summary'];
            $pct = $s['lines_percent'] !== null ? "{$s['lines_percent']}%" : "n/a";
            echo "File '{$f['requested']}' ({$f['report']}): {$pct} executable {$f['executable_lines']} uncovered {$f['uncovered_count']}\n";
            if ($f['uncovered'] !== []) {
                $preview = array_slice($f['uncovered'], 0, 20);
                echo "  uncovered lines: " . implode(', ', $preview);
                if (count($f['uncovered']) > 20) echo " … +" . (count($f['uncovered']) - 20) . " more";
                echo "\n";
                if ($f['uncovered_count'] > 0) echo "  tip: add tests for those lines, then check falsification (make detect-fake + audit-boundaries)\n";
            } else {
                echo "  fully covered (executable lines)\n";
            }
        }
    }
    echo "\n";
}
exit($hasError ? 1 : 0);
