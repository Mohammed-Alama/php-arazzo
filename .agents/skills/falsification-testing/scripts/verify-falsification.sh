#!/usr/bin/env bash
# verify-falsification — orchestrator for the full falsification checklist.
# Runs: fake-detector → pint --test → phpstan → pest → (optional) hume-audit → conformance
# Usage:
#   bash verify-falsification.sh [--quick] [--with-mutate] [--threshold 80] [--filter <pest-filter>]
#   bash verify-falsification.sh --quick            # skip mutate (fast local)
#   bash verify-falsification.sh --with-mutate      # include Hume mutation audit
set -uo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

QUICK=0
WITH_MUTATE=0
THRESHOLD=80
FILTER=""

while [ $# -gt 0 ]; do
  case "$1" in
    --quick) QUICK=1; shift ;;
    --with-mutate) WITH_MUTATE=1; shift ;;
    --threshold) THRESHOLD="$2"; shift 2 ;;
    --filter) FILTER="$2"; shift 2 ;;
    --help|-h) echo "usage: bash verify-falsification.sh [--quick] [--with-mutate] [--threshold N] [--filter <pest-filter>]"; exit 0 ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
done

fail() { echo "!! $1" >&2; exit 1; }

echo "=== verify-falsification ==="
echo "root: $ROOT"
[ -n "$FILTER" ] && echo "filter: $FILTER"
[ "$QUICK" -eq 1 ] && echo "mode: quick (no mutate)"
[ "$WITH_MUTATE" -eq 1 ] && echo "mode: with-mutate (threshold ${THRESHOLD}%)"

# 1) Fake Test Detector
echo
echo "--- 1/5 Fake Test Detector ---"
if [ -n "$FILTER" ]; then
  echo "(skip: filtered run — run without --filter to scan all files)"
else
  php "$SCRIPT_DIR/detect-fake-tests.php" --all || {
    echo "-> fake detector found violations (exit 1). Fix FAKE-* before trusting coverage."
    # non-fatal: continue to show other gates, but mark overall fail
    FAKE_FAIL=1
  }
  FAKE_FAIL=${FAKE_FAIL:-0}
fi

# 2) Style
echo
echo "--- 2/5 pint --test ---"
vendor/bin/pint --test || fail "pint --test failed — run vendor/bin/pint"

# 3) Analysis
echo
echo "--- 3/5 phpstan (max) ---"
composer run analyse || fail "phpstan failed"

# 4) Tests
echo
echo "--- 4/5 pest (random order) ---"
if [ -n "$FILTER" ]; then
  composer run test -- --filter="$FILTER" || fail "pest --filter failed"
else
  composer run test || fail "pest failed"
fi

# 5) Hume / Conformance (optional or quick)
if [ "$WITH_MUTATE" -eq 1 ]; then
  echo
  echo "--- 5/5 Hume mutation audit (threshold ${THRESHOLD}%) ---"
  bash "$SCRIPT_DIR/hume-audit.sh" --all --threshold "$THRESHOLD" || {
    echo "-> Hume audit below threshold — add mutant-killing boundary tests (SKILL.md Pass 2)"
    HUME_FAIL=1
  }
  HUME_FAIL=${HUME_FAIL:-0}
else
  echo
  echo "--- 5/5 skipped (Hume) ---"
  echo "run with --with-mutate to include infection MSI check"
  HUME_FAIL=0
fi

# Conformance sanity if touching Runner/Validator (always cheap)
if [ -z "$FILTER" ]; then
  echo
  echo "--- conformance fixtures ---"
  # Run only conformance suite separately for clearer signal (already covered by composer test, but explicit)
  (cd "$ROOT/packages/core" && vendor/bin/pest tests/Conformance --ci 2>&1 | tail -n 20) || echo "(conformance run: see above)"
fi

echo
if [ "${FAKE_FAIL:-0}" -ne 0 ] || [ "${HUME_FAIL:-0}" -ne 0 ]; then
  echo "verify-falsification: gates passed but falsification checks failed (FAKE=$FAKE_FAIL HUME=$HUME_FAIL) — not done (see SKILL.md checklist)."
  exit 1
fi
echo "verify-falsification: all gates + falsification checks passed — corroboration, not proof."
exit 0
