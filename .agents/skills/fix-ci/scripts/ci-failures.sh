#!/usr/bin/env bash
# Fetch failed GitHub Actions checks and their logs via the gh CLI.
#
# Usage:
#   bash ci-failures.sh                    # current branch's PR, else current branch
#   bash ci-failures.sh --pr 42            # a specific pull request
#   bash ci-failures.sh --branch feat/x    # latest runs on a branch
#   bash ci-failures.sh --run 1234567890   # one specific workflow run
#   bash ci-failures.sh --lines 200        # log tail per failed job (default 120)
#
# Prints a summary of every failed check, then the tail of each failed step's log.
# Exit code: 1 if failures were found, 0 otherwise.

set -uo pipefail

LINES=120
PR=""; BRANCH=""; RUN=""

while [ $# -gt 0 ]; do
  case "$1" in
    --pr)     PR="$2";      shift 2 ;;
    --branch) BRANCH="$2";  shift 2 ;;
    --run)    RUN="$2";     shift 2 ;;
    --lines)  LINES="$2";   shift 2 ;;
    *) echo "unknown argument: $1" >&2; exit 2 ;;
  esac
done

command -v gh >/dev/null || { echo "error: gh CLI not installed" >&2; exit 2; }
command -v jq >/dev/null  || { echo "error: jq not installed" >&2; exit 2; }

PR_NUM=""
SHA=""

if [ -z "$RUN" ]; then
  # Resolve target: explicit PR > explicit branch > current branch's PR > current branch.
  if [ -n "$PR" ]; then
    info=$(gh pr view "$PR" --json number,headRefName,headRefOid) || exit 2
    PR_NUM=$(echo "$info" | jq -r '.number')
    BRANCH=$(echo "$info" | jq -r '.headRefName')
    SHA=$(echo "$info"   | jq -r '.headRefOid')
  else
    if [ -z "$BRANCH" ]; then
      BRANCH=$(git branch --show-current)
    fi
    # A PR may exist for this branch; its head SHA pins the exact commit.
    info=$(gh pr view "$BRANCH" --json number,headRefOid 2>/dev/null) && {
      PR_NUM=$(echo "$info" | jq -r '.number')
      SHA=$(echo "$info"    | jq -r '.headRefOid')
    }
  fi
fi

# gh run list --json emits REST-style lowercase conclusions/statuses.
FAILED_CONCLUSIONS='["failure","timed_out","startup_failure"]'

runs_json="[]"
if [ -n "$RUN" ]; then
  runs_json=$(gh run view "$RUN" --json databaseId,workflowName,displayTitle,conclusion,status,url,event,headSha \
    | jq '[.]' | jq --argjson f "$FAILED_CONCLUSIONS" 'map(select(.conclusion == null or (.conclusion | IN($f[]))))') \
    || { echo "error: run $RUN not found" >&2; exit 2; }
elif [ -n "$SHA" ] && [ "$SHA" != "null" ]; then
  runs_json=$(gh run list --commit "$SHA" --limit 20 --json databaseId,workflowName,displayTitle,conclusion,status,url,event)
else
  runs_json=$(gh run list --branch "$BRANCH" --limit 10 --json databaseId,workflowName,displayTitle,conclusion,status,url,event)
fi

failed=$(echo "$runs_json" | jq --argjson f "$FAILED_CONCLUSIONS" '[.[] | select(.conclusion | IN($f[]))]')
cancelled=$(echo "$runs_json" | jq '[.[] | select(.conclusion == "cancelled")]')

count=$(echo "$failed" | jq 'length')

echo "=== CI status ==="
if [ -n "$PR_NUM" ]; then echo "PR:      #$PR_NUM ($BRANCH)"
elif [ -n "$BRANCH" ]; then echo "Branch:  $BRANCH"; fi
[ -n "$SHA" ] && [ "$SHA" != "null" ] && echo "Commit:  $SHA"
[ -n "$RUN" ] && echo "Run:     $RUN"

if [ "$count" -eq 0 ]; then
  echo "No failed workflow runs found."
else
  echo "Failed runs: $count"
  echo "$failed" | jq -r '.[] | "- \(.workflowName) [\(.conclusion)] \(.displayTitle)\n  \(.url)"'
fi

c_count=$(echo "$cancelled" | jq 'length')
if [ "$c_count" -gt 0 ]; then
  echo
  echo "Cancelled (often concurrency noise):"
  echo "$cancelled" | jq -r '.[] | "- \(.workflowName) \(.displayTitle)\n  \(.url)"'
fi

pending=$(echo "$runs_json" | jq -r '[.[] | select(.status != "completed")] | length')
if [ "$pending" -gt 0 ]; then
  echo
  echo "Still running: $pending (results may be incomplete)"
fi

# Non-Actions checks (commit statuses, external CI) surface here.
if [ -n "$PR_NUM" ]; then
  echo
  echo "=== PR checks ==="
  gh pr checks "$PR_NUM" 2>/dev/null || true
fi

# Dump the tail of each failed step's log.
if [ "$count" -gt 0 ]; then
  echo
  echo "=== Failed step logs (last $LINES lines per run) ==="
  ids=$(echo "$failed" | jq -r '.[].databaseId')
  for id in $ids; do
    name=$(echo "$failed" | jq -r --argjson id "$id" '.[] | select(.databaseId == $id) | .workflowName')
    echo
    echo "--- run $id ($name) ---"
    gh run view "$id" --log-failed 2>&1 | tail -n "$LINES" \
      || echo "(no step logs retrievable for this run)"
  done
  exit 1
fi

exit 0
