#!/usr/bin/env bash
# Prepare a pull request: run the gates, then draft title + body from the
# branch diff. Writes .scratch/pr-body.md.
#
# Usage:
#   bash pr-prep.sh [base] [--create] [--no-gates]
#
# Examples:
#   bash pr-prep.sh                    # base = origin/main
#   bash pr-prep.sh origin/some-branch --create

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && git rev-parse --show-toplevel)"
cd "$ROOT"

BASE="origin/main"
CREATE=0
NO_GATES=0
for arg in "$@"; do
  case "$arg" in
    --create) CREATE=1 ;;
    --no-gates) NO_GATES=1 ;;
    *) BASE="$arg" ;;
  esac
done

BRANCH="$(git branch --show-current)"
if [ -z "$BASE" ]; then BASE="origin/main"; fi
if [ "$BRANCH" = "main" ]; then
  echo "error: you are on main — switch to a feature branch first" >&2
  exit 2
fi

git fetch origin main --quiet 2>/dev/null || true
if ! git rev-parse --verify --quiet "$BASE" >/dev/null; then
  echo "error: base ref '$BASE' not found" >&2
  exit 2
fi

MERGE_BASE="$(git merge-base HEAD "$BASE")"

echo "== branch =="
echo "branch: $BRANCH"
echo "base:   $BASE (merge-base $(git rev-parse --short "$MERGE_BASE"))"

DIRTY="$(git status --porcelain)"
if [ -n "$DIRTY" ]; then
  echo
  echo "WARNING: uncommitted changes (excluded from the PR):"
  echo "$DIRTY" | sed 's/^/  /'
fi

GATE_RESULTS=""
GATES_OK=0
if [ "$NO_GATES" -eq 1 ]; then
  echo
  echo "gates skipped (--no-gates)"
  GATE_RESULTS="- gates: skipped (\`--no-gates\`)"
else
  echo
  if vendor/bin/pint --test; then A=pass; else A=fail; fi
  echo
  if composer run analyse;   then B=pass; else B=fail; fi
  echo
  if composer run test;      then C=pass; else C=fail; fi
  GATE_RESULTS="- \`pint --test\`: ${A}"$'\n'"- \`composer analyse\`: ${B}"$'\n'"- \`composer test\`: ${C}"
  [ "$A" = pass ] && [ "$B" = pass ] && [ "$C" = pass ] && GATES_OK=1
fi

COMMITS="$(git log --oneline "${MERGE_BASE}..HEAD")"
STAT="$(git diff --stat "${MERGE_BASE}...HEAD" | tail -20)"
COUNT="$(git rev-list --count "${MERGE_BASE}..HEAD")"

if [ -z "$COMMITS" ]; then
  echo
  echo "error: no commits between $BASE and HEAD — nothing to prepare" >&2
  exit 1
fi

TITLE="$(git log -1 --format=%s HEAD | sed -E 's/^[a-z]+(\([^)]*\))?!?: //')"

SUMMARY="$(git log --format='- %s' --reverse "${MERGE_BASE}..HEAD" | head -15)"

mkdir -p .scratch
BODY_FILE=".scratch/pr-body.md"
{
  echo "## Summary"
  echo
  echo "$SUMMARY"
  echo
  echo "## Testing"
  echo
  printf '%s' "$GATE_RESULTS"
  echo
  echo "## Diff stat"
  echo
  echo '```'
  echo "$STAT"
  echo '```'
  echo
  echo "_${COUNT} commit(s) against \`${BASE#origin/}\`. Release checklist: docs/release-checklist.md_"
} > "$BODY_FILE"

echo
echo "suggested title: $TITLE"
echo "body written:    $BODY_FILE"
echo
cat "$BODY_FILE"

if [ "$CREATE" -eq 1 ]; then
  if [ "$GATES_OK" -ne 1 ] && [ "$NO_GATES" -ne 1 ]; then
    echo
    echo "error: gates failing — refusing --create" >&2
    exit 1
  fi
  gh pr create --draft --base main --title "$TITLE" --body-file "$BODY_FILE" \
    || { echo "error: gh pr create failed" >&2; exit 1; }
fi

[ "$GATES_OK" -eq 1 ] || [ "$NO_GATES" -eq 1 ]
