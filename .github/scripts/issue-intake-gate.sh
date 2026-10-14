#!/usr/bin/env bash
#
# The CI soft intake gate (spec A4, decision O1).
#
# Deterministic, no LLM, never closes, never assigns. It reaches its verdict
# through tools/issue-intake/bin/validate-issue — the same engine the shim and
# the file-issue skill use (spec D12) — so the shim and CI can never disagree.
#
# Inputs (environment):
#   GH_TOKEN          a token with issues:write
#   GITHUB_REPOSITORY owner/repo (set by Actions)
#   ISSUE_NUMBER      the issue to validate
#   EVENT_NAME        github.event_name (issues | workflow_dispatch)
#   EVENT_ACTION      github.event.action (opened | edited | ...)

set -euo pipefail

MARKER='<!-- intake-gate -->'
REPO="${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
NUMBER="${ISSUE_NUMBER:?ISSUE_NUMBER is required}"

issue_json=$(gh issue view "$NUMBER" --repo "$REPO" --json body,labels)

# ecosystem-labelled issues are automation-owned and explicitly out of scope.
if jq -e 'any(.labels[]; .name == "ecosystem")' <<<"$issue_json" >/dev/null; then
    echo "issue #$NUMBER is ecosystem-labelled; exempt."
    exit 0
fi

# Grandfathering: never mass-label the ~54 legacy issues. Only edits to an
# issue that has opted in with a level:* label are re-validated.
if [ "${EVENT_NAME:-}" = "issues" ] && [ "${EVENT_ACTION:-}" = "edited" ] \
    && ! jq -e 'any(.labels[]; .name | startswith("level:"))' <<<"$issue_json" >/dev/null; then
    echo "legacy issue #$NUMBER edited without a level:* label; skipping."
    exit 0
fi

owner="${REPO%%/*}"
name="${REPO##*/}"

# Resolve the parent (sub-issue) and its level label in one GraphQL call, so a
# story/sub-task invariant can be checked without a second engine.
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
    --argjson labels "$(jq -c '[.labels[].name]' <<<"$issue_json")" \
    --argjson parent "$(jq -c '.data.repository.issue.parent.number // null' <<<"$parent_json")" \
    --argjson parentLevel "$(jq -c '[.data.repository.issue.parent.labels.nodes[]?.name | select(startswith("level:"))][0] // null' <<<"$parent_json")" \
    '{body: $body, labels: $labels, parent: $parent, parentLevel: $parentLevel}')

set +e
php tools/issue-intake/bin/validate-issue >/tmp/result.json 2>/tmp/findings.txt <<<"$payload"
code=$?
set -e

if [ "$code" -eq 2 ]; then
    echo "::error::intake validator exited 2 (internal error)"
    cat /tmp/findings.txt
    exit 1
fi

level=$(jq -r '.level // ""' /tmp/result.json)

# A web-filed issue declares its level only in the body. Apply the label so the
# Epic → Story → Sub-task hierarchy is queryable by label (G5, ruling 7).
if [ -n "$level" ] && ! jq -e --arg label "$level" 'any(.labels[]; .name == $label)' <<<"$issue_json" >/dev/null; then
    echo "applying declared level label: $level"
    gh issue edit "$NUMBER" --repo "$REPO" --add-label "$level"
fi

comments_json=$(gh api "repos/$REPO/issues/$NUMBER/comments" --paginate)
marker_id=$(jq -r --arg marker "$MARKER" \
    '[.[] | select(.body | contains($marker)) | .id] | first // empty' <<<"$comments_json")

if [ "$code" -eq 1 ]; then
    comment=$(printf '%s\n\n%s\n\n%s\n' \
        "$MARKER" \
        'This issue does not yet match the intake structure. Fix the items below, then re-save — the `needs-info` label clears automatically once it conforms.' \
        "$(sed 's/^/- /' /tmp/findings.txt)")

    if [ -n "$marker_id" ]; then
        gh api -X PATCH "repos/$REPO/issues/comments/$marker_id" -f body="$comment" >/dev/null
    else
        gh api -X POST "repos/$REPO/issues/$NUMBER/comments" -f body="$comment" >/dev/null
    fi

    gh issue edit "$NUMBER" --repo "$REPO" --add-label needs-info
    echo "issue #$NUMBER flagged needs-info."
    exit 0
fi

# Conforming: the marker comment records that *we* flagged this issue, so we
# only ever remove needs-info while it is present — never a needs-info that the
# triage skill set for a reason of its own. Drop the note itself once it has
# served that purpose: left behind it is both stale copy and a false ownership
# claim over a needs-info set later.
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
