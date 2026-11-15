#!/usr/bin/env bash
#
# The CI intake gate (spec A4, decision O1) — ratchet from warnings to errors.
#
# Deterministic, no LLM, never closes, never assigns. It reaches its verdict
# through tools/issue-intake/bin/validate-issue — the same engine the shim and
# the file-issue skill use (spec D12) — so the shim and CI can never disagree.
#
# Three states (spec §8.3):
#   grandfathered   no `format:v2` and number ≤ INTAKE_CUTOFF  → skip validation
#   converted       carries `format:v2`                        → errors → needs-info
#   new             number > INTAKE_CUTOFF                     → errors → needs-info
# Warnings only (converted/new) → marker comment "warnings, not blocking", no needs-info.
#
# Inputs (environment):
#   GH_TOKEN          a token with issues:write
#   GITHUB_REPOSITORY owner/repo (set by Actions)
#   ISSUE_NUMBER      the issue to validate
#   EVENT_NAME        github.event_name (issues | workflow_dispatch)
#   EVENT_ACTION      github.event.action (opened | edited | labeled | ...)
#   EVENT_LABEL       github.event.label.name (only meaningful for `labeled`)
#   EVENT_ACTOR       github.actor (set by Actions)
#   INTAKE_CUTOFF     integer; unset or empty ⇒ every issue is grandfathered

set -euo pipefail

MARKER='<!-- intake-gate -->'
REPO="${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
NUMBER="${ISSUE_NUMBER:?ISSUE_NUMBER is required}"
CUTOFF="${INTAKE_CUTOFF:-}"

# Guard: never act on our own label applications or bot noise.
if [ "${EVENT_ACTION:-}" = "labeled" ]; then
    case "${EVENT_LABEL:-}" in
        needs-info|format:v2|level:*) echo "labeled event for ${EVENT_LABEL:-}; self-inflicted, skipping."; exit 0 ;;
    esac
fi
if [ "${EVENT_ACTOR:-}" = "github-actions[bot]" ]; then
    echo "actor is github-actions[bot]; skipping."
    exit 0
fi

issue_json=$(gh issue view "$NUMBER" --repo "$REPO" --json body,labels,title)

# ecosystem-labelled issues are automation-owned and explicitly out of scope.
if jq -e 'any(.labels[]; .name == "ecosystem")' <<<"$issue_json" >/dev/null; then
    echo "issue #$NUMBER is ecosystem-labelled; exempt."
    exit 0
fi

# level:package issues are generated indexes (spec §A7), carry no kind,
# and have no required sections — they are explicitly out of scope.
if jq -e 'any(.labels[]; .name == "level:package")' <<<"$issue_json" >/dev/null; then
    echo "issue #$NUMBER is a package-epic; exempt."
    exit 0
fi

# Legacy edits without a level:* label are never re-validated (triage escape
# hatch). This check runs before grandfathering so legacy skips always win.
if [ "${EVENT_NAME:-}" = "issues" ] && [ "${EVENT_ACTION:-}" = "edited" ] \
    && ! jq -e 'any(.labels[]; .name | startswith("level:"))' <<<"$issue_json" >/dev/null; then
    echo "legacy issue #$NUMBER edited without a level:* label; skipping."
    exit 0
fi

# Determine ratchet state.
has_format_v2() { jq -e --arg name "format:v2" 'any(.labels[]; .name == $name)' <<<"$issue_json" >/dev/null; }
is_grandfathered() {
    # Unset/empty cutoff ⇒ everything is grandfathered (Phase 1 ships clean).
    [ -z "$CUTOFF" ] && return 0
    # Below or at the cutoff, and not opted in with format:v2.
    [ "$NUMBER" -le "$CUTOFF" ] && ! has_format_v2 && return 0
    return 1
}

if is_grandfathered; then
    echo "issue #$NUMBER is grandfathered (cutoff=${CUTOFF:-unset}); skipping validation."
    exit 0
fi

owner="${REPO%%/*}"
name="${REPO##*/}"

# Resolve the parent (sub-issue) and its level label in one GraphQL call.
parent_json=$(gh api graphql -f query='
    query($owner: String!, $repo: String!, $number: Int!) {
      repository(owner: $owner, name: $repo) {
        issue(number: $number) {
          parent { number labels(first: 50) { nodes { name } } }
        }
      }
    }' -F owner="$owner" -F repo="$name" -F number="$NUMBER")

payload=$(jq -n \
    --arg body "$(jq -r '.body // ""' <<<"$issue_json")" \
    --arg title "$(jq -r '.title // ""' <<<"$issue_json")" \
    --argjson labels "$(jq -c '[.labels[].name]' <<<"$issue_json")" \
    --argjson parent "$(jq -c '.data.repository.issue.parent.number // null' <<<"$parent_json")" \
    --argjson parentLevel "$(jq -c '[.data.repository.issue.parent.labels.nodes[]?.name | select(startswith("level:"))][0] // null' <<<"$parent_json")" \
    '{body: $body, title: $title, labels: $labels, parent: $parent, parentLevel: $parentLevel}')

set +e
php tools/issue-intake/bin/validate-issue >/tmp/result.json 2>/tmp/findings.txt <<<"$payload"
code=$?
set -e

level=$(jq -r '.level // ""' /tmp/result.json)
ready=$(jq -r '.ready // false' /tmp/result.json)
blockers_json=$(jq -c '.blockers // []' /tmp/result.json)
blocker_count=$(jq length <<<"$blockers_json")

# Does the issue carry the ready-for-agent label?
has_ready_agent() { jq -e --arg name "ready-for-agent" 'any(.labels[]; .name == $name)' <<<"$issue_json" >/dev/null; }

# Apply declared level label so the Epic → Story → Sub-task hierarchy is queryable.
if [ -n "$level" ] && ! jq -e --arg label "$level" 'any(.labels[]; .name == $label)' <<<"$issue_json" >/dev/null; then
    echo "applying declared level label: $level"
    gh issue edit "$NUMBER" --repo "$REPO" --add-label "$level"
fi

comments_json=$(gh api "repos/$REPO/issues/$NUMBER/comments" --paginate)
marker_id=$(jq -r --arg marker "$MARKER" \
    '[.[] | select(.body | contains($marker)) | .id] | first // empty' <<<"$comments_json")

# Separate error-severity findings from warnings.
error_count=$(jq '[.findings[] | select(.severity == "error")]' /tmp/result.json | jq length)
warning_count=$(jq '[.findings[] | select(.severity == "warning")]' /tmp/result.json | jq length)

needs_info=false
body_parts=()

# Errors → needs-info.
if [ "$error_count" -gt 0 ]; then
    needs_info=true
    body_parts+=("This issue does not yet match the intake structure ($(date +%Y-%m-%d)). Fix the error items below, then re-save — the \`needs-info\` label clears automatically once it conforms.")
    body_parts+=("$(jq -r '.findings[] | select(.severity == "error") | "- [\(.code)] \(.message)"' /tmp/result.json)")
fi

# Readiness is evaluated independently of conformance: only when the issue
# opted in with ready-for-agent; a failing readiness removes that label,
# adds needs-info, and lists blockers in the marker comment.
if has_ready_agent && [ "$ready" = "false" ] && [ "$blocker_count" -gt 0 ]; then
    needs_info=true
    gh issue edit "$NUMBER" --repo "$REPO" --remove-label ready-for-agent
    echo "issue #$NUMBER removed ready-for-agent."
    body_parts+=("Readiness is not met. Blocker(s): $(jq -r '.[]' <<<"$blockers_json" | paste -sd ', ' -).")
fi

if [ "$needs_info" = true ]; then
    comment=$(printf '%s\n\n%s\n\n' "$MARKER" "$(printf '%s\n' "${body_parts[@]}")")

    if [ -n "$marker_id" ]; then
        gh api -X PATCH "repos/$REPO/issues/comments/$marker_id" -f body="$comment" >/dev/null
    else
        gh api -X POST "repos/$REPO/issues/$NUMBER/comments" -f body="$comment" >/dev/null
    fi

    gh issue edit "$NUMBER" --repo "$REPO" --add-label needs-info
    echo "issue #$NUMBER flagged needs-info ($error_count error(s), $blocker_count readiness blocker(s))."
    exit 0
fi

# No errors, no readiness failure. Handle warnings.
if [ "$warning_count" -gt 0 ]; then
    comment=$(printf '%s\n\n%s\n\n%s\n' \
        "$MARKER" \
        "This issue conforms but has $(date +%Y-%m-%d) warnings — review before scheduling. None block conversion." \
        "$(jq -r '.findings[] | select(.severity == "warning") | "- [\(.code)] \(.message)"' /tmp/result.json)")

    if [ -n "$marker_id" ]; then
        gh api -X PATCH "repos/$REPO/issues/comments/$marker_id" -f body="$comment" >/dev/null
    else
        gh api -X POST "repos/$REPO/issues/$NUMBER/comments" -f body="$comment" >/dev/null
    fi

    echo "issue #$NUMBER has warnings only ($warning_count warning(s)); not blocking."
    exit 0
fi

# Fully conforming, no warnings.
if [ -n "$marker_id" ]; then
    if jq -e 'any(.labels[]; .name == "needs-info")' <<<"$issue_json" >/dev/null; then
        gh issue edit "$NUMBER" --repo "$REPO" --remove-label needs-info
        echo "issue #$NUMBER conforms; cleared needs-info."
    fi

    gh api -X DELETE "repos/$REPO/issues/comments/$marker_id" >/dev/null
    echo "issue #$NUMBER conforms; removed the stale intake note."
else
    echo "issue #$NUMBER conforms; nothing to clear."
fi
