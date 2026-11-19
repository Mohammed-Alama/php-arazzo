#!/usr/bin/env bash
# generate-coverage.sh — regenerate Pest coverage reports via phpdbg (no pcov/xdebug needed)
# Usage: bash generate-coverage.sh [--core|--laravel|--all] [--json] [--open]
#   --core      core only (default is --all)
#   --laravel   laravel only
#   --all       both packages
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

run_one() {
  local pkg="$1"
  local dir="$ROOT/packages/$pkg"
  local report="$dir/coverage-report"
  local clover="$dir/coverage.xml"
  echo "=== [$pkg] phpdbg -qrr pest --coverage ==="
  echo "  report: $report"
  mkdir -p "$report"
  # phpdbg is the Herd phpdbg (has coverage without pcov)
  if ! command -v phpdbg >/dev/null 2>&1; then
    echo "error: phpdbg not found" >&2; return 2
  fi
  # Use phpdbg -qrr to run pest with coverage; --coverage-html and --coverage-clover are phpunit options proxied via pest
  set +e
  (cd "$dir" && phpdbg -qrr vendor/bin/pest --coverage --coverage-html="$report" --coverage-clover="$clover" 2>&1 | tail -n 50)
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

FAIL=0
if [ "$PKG" = "all" ]; then
  run_one core || FAIL=1
  run_one laravel || FAIL=1
elif [ "$PKG" = "core" ]; then
  run_one core || FAIL=1
elif [ "$PKG" = "laravel" ]; then
  run_one laravel || FAIL=1
fi

# Summarize via query-coverage if available
if [ "$JSON" -eq 1 ]; then
  echo
  echo "=== coverage summary (json) ==="
  php "$ROOT/.agents/skills/coverage-insights/scripts/query-coverage.php" --overview --package "$PKG" --json 2>&1 | tee "$ROOT/coverage-summary.json"
fi

if [ "$OPEN" -eq 1 ]; then
  for pkg in $( [ "$PKG" = "all" ] && echo "core laravel" || echo "$PKG" ); do
    report="$ROOT/packages/$pkg/coverage-report/index.html"
    if [ -f "$report" ]; then
      echo "opening $report"
      open "$report" 2>/dev/null || xdg-open "$report" 2>/dev/null || echo "open $report manually"
    fi
  done
fi

exit $FAIL
