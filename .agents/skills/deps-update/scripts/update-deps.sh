#!/usr/bin/env bash
# Update composer dependencies across all three roots, then re-run the gates.
#
# Usage:
#   bash update-deps.sh [--no-gates] [--outdated]
#
# Notes:
# - Only the root composer.lock is tracked; packages/*/composer.lock are
#   local-only (gitignored) but still refreshed here.
# - CI additionally exercises the Laravel 12/13 matrix via require-hints;
#   locally only one combination is tested.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && git rev-parse --show-toplevel)"
cd "$ROOT"

NO_GATES=0
OUTDATED=0
for arg in "$@"; do
  case "$arg" in
    --no-gates) NO_GATES=1 ;;
    --outdated) OUTDATED=1 ;;
    *) echo "unknown argument: $arg" >&2; exit 2 ;;
  esac
done

run_gates() {
  local failed=0
  echo "== gate: pint =="
  vendor/bin/pint --test || failed=1
  echo "== gate: phpstan =="
  composer run analyse || failed=1
  echo "== gate: pest =="
  composer run test || failed=1
  return "$failed"
}

for dir in . packages/core packages/laravel; do
  echo "== composer update: ${dir#.}/ == "
  (cd "$dir" && composer update --no-interaction --no-progress) || {
    echo "error: composer update failed in $dir" >&2
    exit 1
  }
done

echo
echo "== root lock drift (tracked) =="
git diff --stat -- composer.lock || true
echo "packages/*/composer.lock updated locally (untracked by design)"

if [ "$OUTDATED" -eq 1 ]; then
  for dir in . packages/core packages/laravel; do
    echo
    echo "== outdated direct deps: ${dir#.}/ =="
    (cd "$dir" && composer outdated --direct) || true
  done
fi

if [ "$NO_GATES" -eq 1 ]; then
  echo
  echo "gates skipped (--no-gates)"
  exit 0
fi

echo
run_gates
status=$?
if [ "$status" -ne 0 ]; then
  echo
  echo "gates FAILED after update. Revert with:"
  echo "  git checkout -- composer.lock"
  echo "  (package locks: cd <pkg> && composer install to restore from their lock)"
fi
exit "$status"
