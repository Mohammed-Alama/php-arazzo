#!/usr/bin/env bash
# generate-coverage.sh — regenerate Pest coverage reports (root vendor + pcov).
# Usage: bash generate-coverage.sh [--core|--laravel|--all] [--json] [--open]
#   --core      core only (note: packages/core/src has no PHP source — N/A)
#   --laravel   laravel only
#   --all       root-level all-packages report + laravel per-package
#   --json      machine-readable summary to stdout (also written to coverage-summary.json)
#   --open      open HTML report in browser after generation
set -u

ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || echo "")"
if [ -z "$ROOT" ] || [ ! -f "$ROOT/packages/core/composer.json" ]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
fi

PKG="all"
JSON=0
OPEN=0
while [ $# -gt 0 ]; do
  case "$1" in
    --core) PKG="core"; shift ;;
    --laravel) PKG="laravel"; shift ;;
    --all) PKG="all"; shift ;;
    --json) JSON=1; shift ;;
    --open) OPEN=1; shift ;;
    --help|-h) echo "usage: bash generate-coverage.sh [--core|--laravel|--all] [--json] [--open]"; exit 0 ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
done

# Run coverage for a single package that has its own phpunit.xml.dist.
# Only laravel has one today; core has no production source.
run_pkg() {
  local pkg="$1"
  local dir="$ROOT/packages/$pkg"
  local report="$dir/coverage-report"
  local clover="$dir/coverage.xml"
  echo "=== [$pkg] pest --coverage (root vendor + pcov) ==="
  echo "  report: $report"
  echo "  config: packages/$pkg/phpunit.xml.dist"
  mkdir -p "$report"
  if [ ! -f "$ROOT/vendor/bin/pest" ]; then
    echo "error: vendor/bin/pest not found — run composer install first" >&2; return 2
  fi
  if [ ! -f "$dir/phpunit.xml.dist" ]; then
    echo "warning: packages/$pkg/phpunit.xml.dist not found — skipping $pkg" >&2; return 0
  fi
  set +e
  (cd "$ROOT" && php -d memory_limit=1G "$ROOT/vendor/bin/pest" \
      -c "packages/$pkg/phpunit.xml.dist" \
      --bootstrap="$ROOT/vendor/autoload.php" \
      --coverage --coverage-html="$report" --coverage-clover="$clover" \
      "packages/$pkg/tests" 2>&1 | tail -n 60)
  local ec=$?
  set -e
  if [ -f "$report/index.html" ]; then
    echo "  generated: $report/index.html"
  else
    echo "  warning: $report/index.html not found (pest exit $ec)" >&2
  fi
  if [ -f "$clover" ]; then
    echo "  generated: $clover"
  fi
  return $ec
}

# Run root-level coverage for ALL packages at once.
# The root phpunit.xml.dist includes every package's src/ in its coverage source filter,
# so a single run covers the entire monorepo. A high-memory config is needed because
# the HTML writer exceeds the default 512M phpunit limit.
run_all() {
  local report="$ROOT/coverage-report-all"
  local clover="$ROOT/coverage.xml-all"
  local tmpcfg="$ROOT/phpunit-coverage.temp.dist"
  echo "=== [all] pest --coverage (root vendor + pcov, all packages) ==="
  echo "  report: $report"
  echo "  config: phpunit.xml.dist (root, all packages)"
  mkdir -p "$report"
  if [ ! -f "$ROOT/vendor/bin/pest" ]; then
    echo "error: vendor/bin/pest not found — run composer install first" >&2; return 2
  fi
  # phpunit.xml.dist caps memory_limit at 512M which is too low for the HTML writer.
  # Copy to a temp config with 2G, run, then delete.
  cp "$ROOT/phpunit.xml.dist" "$tmpcfg"
  perl -pi -e 's/value="512M"/value="2G"/' "$tmpcfg"
  set +e
  (cd "$ROOT" && php -d memory_limit=3G "$ROOT/vendor/bin/pest" \
      -c "$tmpcfg" \
      --bootstrap="$ROOT/vendor/autoload.php" \
      --coverage --coverage-html="$report" --coverage-clover="$clover" \
      2>&1 | tail -n 60)
  local ec=$?
  set -e
  rm -f "$tmpcfg"
  if [ -f "$report/index.html" ]; then
    echo "  generated: $report/index.html"
  else
    echo "  warning: $report/index.html not found (pest exit $ec)" >&2
  fi
  if [ -f "$clover" ]; then
    echo "  generated: $clover"
  fi
  return $ec
}

FAIL=0
if [ "$PKG" = "all" ]; then
  run_all || FAIL=1
  run_pkg laravel || FAIL=1
elif [ "$PKG" = "laravel" ]; then
  run_pkg laravel || FAIL=1
elif [ "$PKG" = "core" ]; then
  echo "=== [core] no production source (packages/core/src is committed-empty) — N/A ==="
else
  run_pkg "$PKG" || FAIL=1
fi

# Summarize via query-coverage if available
if [ "$JSON" -eq 1 ]; then
  echo
  echo "=== coverage summary (json) ==="
  if [ "$PKG" = "all" ]; then
    # Root-level report covers every package at once (coverage-report-all/).
    # Per-package breakdown from coverage-report-all/packages/*/index.html
    # (each package's data-coverage blob) + overall total from root index.html.
    ROOT="$ROOT" php -r '
      $root = getenv("ROOT") ?: "";
      $reportDir = $root . "/coverage-report-all";
      $pkgDir = $reportDir . "/packages";
      if (!is_dir($pkgDir)) {
        echo json_encode(["package"=>"all","error"=>"coverage-report-all/packages/ not found — run make coverage first"]); exit(1);
      }
      $packages = [];
      $grandCovered = 0; $grandTotal = 0;
      $grandMethodsCovered = 0; $grandMethodsTotal = 0;
      $grandClassesCovered = 0; $grandClassesTotal = 0;
      foreach (new DirectoryIterator($pkgDir) as $dir) {
        if (!$dir->isDir() || $dir->isDot()) continue;
        $name = $dir->getBasename();
        $idx = $dir->getPathname() . "/index.html";
        if (!file_exists($idx)) continue;
        $html = @file_get_contents($idx);
        if ($html === false) continue;
        // Match data-coverage blob on raw HTML (entities still intact), then decode just the blob
        $data = null;
        if (preg_match("/data-coverage=\"([^\"]+)\"/", $html, $m)) {
          $blob = html_entity_decode($m[1]);
          $data = json_decode($blob, true);
        }
        if (!is_array($data)) continue;
        $covered = (int)($data["linesAll"] ?? 0);
        $total   = (int)($data["linesTotal"] ?? 0);
        $mCovered = (int)($data["methodsAll"] ?? 0);
        $mTotal   = (int)($data["methodsTotal"] ?? 0);
        $cCovered = (int)($data["classesAll"] ?? 0);
        $cTotal   = (int)($data["classesTotal"] ?? 0);
        $pct = $total > 0 ? round(($covered / $total) * 100, 2) : null;
        $packages[$name] = [
          "lines_percent" => $pct,
          "lines_covered" => $covered,
          "lines_total" => $total,
          "methods_percent" => $mTotal > 0 ? round(($mCovered / $mTotal) * 100, 2) : null,
          "methods_covered" => $mCovered,
          "methods_total" => $mTotal,
          "classes_percent" => $cTotal > 0 ? round(($cCovered / $cTotal) * 100, 2) : null,
          "classes_covered" => $cCovered,
          "classes_total" => $cTotal,
          "report" => "coverage-report-all/packages/$name/index.html",
        ];
        $grandCovered += $covered; $grandTotal += $total;
        $grandMethodsCovered += $mCovered; $grandMethodsTotal += $mTotal;
        $grandClassesCovered += $cCovered; $grandClassesTotal += $cTotal;
      }
      ksort($packages);
      $grandPct = $grandTotal > 0 ? round(($grandCovered / $grandTotal) * 100, 2) : null;
      echo json_encode([
        "package" => "all",
        "total" => [
          "lines_percent" => $grandPct,
          "lines_covered" => $grandCovered,
          "lines_total" => $grandTotal,
          "methods_percent" => $grandMethodsTotal > 0 ? round(($grandMethodsCovered / $grandMethodsTotal) * 100, 2) : null,
          "methods_covered" => $grandMethodsCovered,
          "methods_total" => $grandMethodsTotal,
          "classes_percent" => $grandClassesTotal > 0 ? round(($grandClassesCovered / $grandClassesTotal) * 100, 2) : null,
          "classes_covered" => $grandClassesCovered,
          "classes_total" => $grandClassesTotal,
        ],
        "packages" => $packages,
        "report" => "coverage-report-all/index.html",
        "dashboard" => "coverage-report-all/dashboard.html",
        "laravel_report" => "packages/laravel/coverage-report/index.html",
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    ' | tee "$ROOT/storage/coverage-summary.json"
  else
    php "$ROOT/.agents/skills/coverage-insights/scripts/query-coverage.php" --overview --package "$PKG" --json 2>&1 | tee "$ROOT/storage/coverage-summary.json"
  fi
fi

if [ "$OPEN" -eq 1 ]; then
  if [ "$PKG" = "all" ]; then
    report="$ROOT/coverage-report-all/index.html"
    [ -f "$report" ] && { echo "opening $report"; open "$report" 2>/dev/null || xdg-open "$report" 2>/dev/null || echo "open $report manually"; }
    report="$ROOT/packages/laravel/coverage-report/index.html"
    [ -f "$report" ] && { echo "opening $report"; open "$report" 2>/dev/null || xdg-open "$report" 2>/dev/null || echo "open $report manually"; }
  else
    report="$ROOT/packages/$PKG/coverage-report/index.html"
    [ -f "$report" ] && { echo "opening $report"; open "$report" 2>/dev/null || xdg-open "$report" 2>/dev/null || echo "open $report manually"; }
  fi
fi

exit $FAIL
