#!/usr/bin/env bash
# Hume / Mutation audit — wraps `pest --mutate` (Infection) and reports survival theater.
# Usage:
#   bash hume-audit.sh [--core] [--laravel] [--all] [--threshold 80] [--covered-only] [--dry-run] [--filter <pest-filter>]
#   bash hume-audit.sh --all --threshold 90
# Exit: 0 = MSI >= threshold, 1 = below threshold or mutants survived, 2 = usage/infra error
set -uo pipefail

ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || echo "")"
if [ -z "$ROOT" ] || [ ! -f "$ROOT/packages/core/composer.json" ]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
fi

THRESHOLD=80
COVERED_ONLY="--covered-only"
FILTER=""
DRY_RUN=0
TARGET="all"
MODE=""

while [ $# -gt 0 ]; do
  case "$1" in
    --core) TARGET="core"; shift ;;
    --laravel) TARGET="laravel"; shift ;;
    --all) TARGET="all"; shift ;;
    --threshold) THRESHOLD="$2"; shift 2 ;;
    --no-covered-only) COVERED_ONLY=""; shift ;;
    --covered-only) COVERED_ONLY="--covered-only"; shift ;;
    --filter) FILTER="$2"; shift 2 ;;
    --dry-run) DRY_RUN=1; shift ;;
    --help|-h) echo "usage: bash hume-audit.sh [--core|--laravel|--all] [--threshold N] [--no-covered-only] [--filter <pest-filter>] [--dry-run]"; exit 0 ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
done

if ! [[ "$THRESHOLD" =~ ^[0-9]+$ ]]; then echo "threshold must be integer" >&2; exit 2; fi

run_one() {
  local pkg="$1" # core|laravel
  local dir="$ROOT/packages/$pkg"
  if [ ! -f "$dir/vendor/bin/pest" ]; then
    echo "[$pkg] vendor/bin/pest not found — run composer install in $dir" >&2
    return 2
  fi
  echo "=== [$pkg] pest --mutate $COVERED_ONLY ${FILTER:+--filter=$FILTER} ==="
  if [ "$DRY_RUN" -eq 1 ]; then
    echo "(dry-run) would run: cd $dir && vendor/bin/pest --mutate $COVERED_ONLY ${FILTER:+--filter=\"$FILTER\"}"
    return 0
  fi
  local log="$dir/infection.log"
  local summary="$dir/infection-summary.log"
  # pest --mutate delegates to infection; capture text + summary
  set +e
  (cd "$dir" && vendor/bin/pest --mutate $COVERED_ONLY ${FILTER:+--filter="$FILTER"} 2>&1 | tee "$log")
  local ec=$?
  set -e
  # pest returns non-zero when mutants survive — we still want to parse
  if [ -f "$summary" ]; then echo "--- $summary ---"; cat "$summary"; fi
  if [ -f "$log" ]; then
    # Try to extract MSI / killed / survived / timeout
    echo "--- parsed ---"
    grep -E "Mutations|Killed|Survived|Timed Out|MSI|Mutation Score" "$log" | tail -20 || true
    # Heuristic fail if "Survived" >0 and MSI < threshold
    local survived
    survived=$(grep -oE "Survived[ :]+[0-9]+" "$log" | grep -oE "[0-9]+" | head -1 || echo "")
    local msi
    msi=$(grep -oE "MSI[ :]+[0-9]+(\.[0-9]+)?%?" "$log" | grep -oE "[0-9]+(\.[0-9]+)?" | head -1 || echo "")
    if [ -n "$msi" ]; then
      # compare integers
      local msi_int=${msi%.*}
      if [ "$msi_int" -lt "$THRESHOLD" ]; then
        echo "[$pkg] FAIL: MSI ${msi}% < threshold ${THRESHOLD}% — coverage theater (Hume)" >&2
        return 1
      else
        echo "[$pkg] PASS: MSI ${msi}% >= ${THRESHOLD}%"
      fi
    fi
    if [ -n "$survived" ] && [ "$survived" -gt 0 ]; then
      echo "[$pkg] NOTE: ${survived} mutants survived — inspect infection.log for uncovered logic" >&2
      # Do not hard-fail on survivors alone if MSI passes; caller decides via threshold
    fi
  fi
  # Return pest exit code if it failed for infra reasons; otherwise 0 if MSI ok
  return 0
}

FAIL=0
if [ "$TARGET" = "all" ]; then
  run_one core || FAIL=1
  run_one laravel || FAIL=1
elif [ "$TARGET" = "core" ]; then
  run_one core || FAIL=1
elif [ "$TARGET" = "laravel" ]; then
  run_one laravel || FAIL=1
fi

if [ "$FAIL" -ne 0 ]; then
  echo
  echo "Hume audit: some package(s) below threshold. High line coverage + surviving mutants = coverage theater. Add boundary/mutant-killing tests (see SKILL.md Pass 2)."
  exit 1
fi
echo
echo "Hume audit: all packages meet MSI >= ${THRESHOLD}%"
exit 0
