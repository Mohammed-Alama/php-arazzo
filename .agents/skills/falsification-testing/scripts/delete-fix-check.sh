#!/usr/bin/env bash
# Delete-the-fix check — the highest-signal falsification test.
# Temporarily hides the fix (git stash) and runs the relevant Pest tests.
# If the suite still passes without the fix, the tests are fake.
#
# Usage:
#   bash delete-fix-check.sh [--filter <pest-filter>] [--path <packages/core|packages/laravel>] [--no-stash] [--keep-stash]
#   bash delete-fix-check.sh --filter "marks pending_retry on timeout"
#   bash delete-fix-check.sh --filter "step.dependson_no_cycle"
#
# By default it stashes current uncommitted changes (the fix), runs tests, then restores.
# Use --no-stash if you already stashed or want to compare committed HEAD vs working tree.
set -uo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
FILTER=""
PKGPATH="packages/core"
NO_STASH=0
KEEP_STASH=0

while [ $# -gt 0 ]; do
  case "$1" in
    --filter) FILTER="$2"; shift 2 ;;
    --path) PKGPATH="$2"; shift 2 ;;
    --no-stash) NO_STASH=1; shift ;;
    --keep-stash) KEEP_STASH=1; shift ;;
    --help|-h) echo "usage: bash delete-fix-check.sh [--filter <pest-filter>] [--path <path>] [--no-stash] [--keep-stash]"; exit 0 ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
done

if [ ! -d "$ROOT/$PKGPATH" ] && [ ! -f "$ROOT/$PKGPATH" ]; then
  echo "path not found: $PKGPATH" >&2; exit 2
fi

STASHED=0
if [ "$NO_STASH" -eq 0 ]; then
  if ! git -C "$ROOT" diff --quiet || ! git -C "$ROOT" diff --cached --quiet; then
    echo "==> stashing fix (uncommitted changes) ..."
    git -C "$ROOT" stash push -m "delete-fix-check: temp hide fix" --keep-index 2>&1 | sed 's/^/    /'
    STASHED=1
  else
    echo "==> no uncommitted changes to stash — comparing HEAD (without fix if fix is uncommitted, you need to commit or use --no-stash after stash)"
    echo "    tip: make a commit with the fix, then run: git stash push -m fix && bash delete-fix-check.sh --filter \"...\" --no-stash"
  fi
fi

# Determine pest invocation
PEST_DIR=""
PEST_FILTER_ARG=""
if [ -n "$FILTER" ]; then PEST_FILTER_ARG="--filter=\"$FILTER\""; fi

if [[ "$PKGPATH" == *"packages/core"* ]]; then
  PEST_DIR="$ROOT/packages/core"
elif [[ "$PKGPATH" == *"packages/laravel"* ]]; then
  PEST_DIR="$ROOT/packages/laravel"
else
  # infer from filter path or default to core
  PEST_DIR="$ROOT/packages/core"
fi

if [ ! -x "$PEST_DIR/vendor/bin/pest" ]; then
  echo "pest not found at $PEST_DIR/vendor/bin/pest" >&2
  [ "$STASHED" -eq 1 ] && [ "$KEEP_STASH" -eq 0 ] && git -C "$ROOT" stash pop 2>&1 | sed 's/^/    /'
  exit 2
fi

echo "==> running Pest WITHOUT the fix (should go RED if tests are real) ..."
echo "    dir: $PEST_DIR  filter: ${FILTER:-<none>}"

set +e
if [ -n "$FILTER" ]; then
  (cd "$PEST_DIR" && vendor/bin/pest --filter="$FILTER" 2>&1 | tail -n 80)
  EC=$?
else
  (cd "$PEST_DIR" && vendor/bin/pest 2>&1 | tail -n 80)
  EC=$?
fi
set -e

echo
if [ "$EC" -eq 0 ]; then
  echo "RESULT: tests STILL PASSED without the fix — fake tests (FAKE-5). None of them falsify this fix."
  echo "Action: add a test that fails without the fix — assert on the exact observable (status/Transition/stepResults), not not->toBeNull or call counts."
  OUT=1
else
  echo "RESULT: tests FAILED without the fix (exit $EC) — good. At least one test falsifies the fix."
  OUT=0
fi

if [ "$STASHED" -eq 1 ] && [ "$KEEP_STASH" -eq 0 ]; then
  echo
  echo "==> restoring fix (stash pop) ..."
  git -C "$ROOT" stash pop 2>&1 | sed 's/^/    /'
  echo "==> re-running WITH the fix (should go GREEN) ..."
  set +e
  if [ -n "$FILTER" ]; then
    (cd "$PEST_DIR" && vendor/bin/pest --filter="$FILTER" 2>&1 | tail -n 30)
    EC2=$?
  else
    (cd "$PEST_DIR" && vendor/bin/pest 2>&1 | tail -n 30)
    EC2=$?
  fi
  set -e
  if [ "$EC2" -eq 0 ]; then echo "WITH fix: GREEN — corroboration stronger."; else echo "WITH fix: still RED (exit $EC2) — fix incomplete or test broken."; OUT=1; fi
fi

exit $OUT
