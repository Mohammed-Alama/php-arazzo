#!/usr/bin/env bash
#
# package-index.sh — regenerate the living index inside every package-epic
# (spec §A7, D15, D16).
#
# Reads the package-epic issue (or all open package-epics on a sweep),
# queries its child epics and their story counts, renders the index via
# render-package-index, and writes it back only when the body changes.
# Also enforces the ≤1 open package-epic per package:* invariant.

set -euo pipefail

die() { printf 'package-index: %s\n' "$1" >&2; exit "${2:-1}"; }

# The renderer is invoked relative to the repo root (working directory in CI / tests).
RENDERER="tools/issue-intake/bin/render-package-index"

[ -f "$RENDERER" ] || die "renderer not found at $RENDERER"



# GitHub GraphQL queries
QUERY_EPICS='query($owner: String!, $repo: String!, $number: Int!) {
  repository(owner: $owner, name: $repo) {
    issue(number: $number) {
      number
      body
      labels(first: 50) { nodes { name } }
      subIssues(first: 100) {
        nodes { number title state subIssues { totalCount } }
      }
    }
  }
}'

QUERY_PARENT='query($owner: String!, $repo: String!, $number: Int!) {
  repository(owner: $owner, name: $repo) {
    issue(number: $number) {
      parent { number }
    }
  }
}'

IFS=/ read -r OWNER REPO <<<"$GITHUB_REPOSITORY"
ISSUE_NUMBER="${ISSUE_NUMBER:-}"



# Resolve target package-epic(s)
targets=()

if [ -n "$ISSUE_NUMBER" ]; then
    # Single issue mode: find the package-epic by walking up parents
    json=$(gh api graphql -f query="$QUERY_EPICS" -F owner="$OWNER" -F repo="$REPO" -F number="$ISSUE_NUMBER")
    while true; do
        level=$(jq -r '[.data.repository.issue.labels.nodes[].name | select(startswith("level:"))][0] // empty' <<<"$json")
        case "$level" in
            level:package) break ;;
            level:epic|level:story|level:task)
                # Walk to parent
                parent=$(jq -r '.data.repository.issue.parent.number // empty' <<<"$json")
                [ -n "$parent" ] || die "issue #$ISSUE_NUMBER is $level but has no parent"
                json=$(gh api graphql -f query="$QUERY_PARENT" -F owner="$OWNER" -F repo="$REPO" -F number="$parent" 2>/dev/null || echo '{"data":{"repository":{"issue":null}}}')
                json=$(gh api graphql -f query="$QUERY_EPICS" -F owner="$OWNER" -F repo="$REPO" -F number="$parent")
                ;;
            *) die "issue #$ISSUE_NUMBER has no level label" ;;
        esac
    done
    targets+=("$json")
else
    # Sweep mode: all open level:package issues
    list=$(gh issue list --label level:package --state open --json number)
    for num in $(jq -r '.[].number' <<<"$list"); do
        json=$(gh api graphql -f query="$QUERY_EPICS" -F owner="$OWNER" -F repo="$REPO" -F number="$num")
        targets+=("$json")
    done
fi

# Uniqueness check (sweep mode only)
if [ -z "$ISSUE_NUMBER" ]; then
    list=$(gh issue list --label level:package --state open --json number,labels)
    # Group by package:* (guard against null label names)
    echo "$list" | jq -c 'group_by(.labels[].name | select(. != null and startswith("package:"))) | map(select(length > 1)) | .[]' | while read -r group; do
        nums=$(jq -r '.[].number' <<<"$group")
        pkg=$(jq -r '.[0].labels[].name | select(. != null and startswith("package:"))' <<<"$group")
        # Find or create the marker comment on the first one
        first=$(head -n1 <<<"$nums")
        comments=$(gh api "repos/$OWNER/$REPO/issues/$first/comments" --paginate 2>/dev/null || echo '[]')
        marker_id=$(jq -r 'map(select((.body // "") | contains("<!-- package-index -->"))) | .[0].id // empty' <<<"$comments")
        if [ -z "$marker_id" ]; then
            gh api "repos/$OWNER/$REPO/issues/$first/comments" -f body="<!-- package-index --> Collision: multiple open package-epics for package $pkg. Keep only one." >/dev/null
        fi
        # Add needs-info to all
        for n in $nums; do
            gh issue edit "$n" --repo "$OWNER/$REPO" --add-label needs-info >/dev/null
        done
        echo "package-index: flagged $pkg (issues: $nums) with needs-info"
    done
fi

# Process each target
for json in "${targets[@]}"; do
    number=$(jq -r '.data.repository.issue.number' <<<"$json")
    [ "$number" = "null" ] && continue

    pkg=$(jq -r '[.data.repository.issue.labels.nodes[].name | select(startswith("package:"))][0] // empty' <<<"$json")
    [ -n "$pkg" ] || { echo "package-index: issue #$number has no package:* label, skipping"; continue; }
    # Strip 'package:' prefix for display
    pkg_display="${pkg#package:}"

    body=$(jq -r '.data.repository.issue.body // ""' <<<"$json")

    epics=$(jq -c '[.data.repository.issue.subIssues.nodes[]? | {number, title, state, stories: (.subIssues.totalCount // 0)}]' <<<"$json")

    payload=$(jq -n \
        --arg package "$pkg_display" \
        --arg body "$body" \
        --argjson epics "$epics" \
        '{package: $package, body: $body, epics: $epics}')

    rendered=$(php "$RENDERER" <<<"$payload")
    new_body=$(jq -r '.body' <<<"$rendered")

    # Do our own comparison to avoid JSON round-trip issues with renderer's 'changed' flag
    if [ "$new_body" = "$body" ]; then
        changed="false"
    else
        changed="true"
    fi

    if [ "$changed" = "true" ]; then
        file=$(mktemp "${TMPDIR:-/tmp}/package-index-body.XXXXXX")
        printf '%s' "$new_body" > "$file"
        gh issue edit "$number" --repo "$OWNER/$REPO" --body-file "$file" >/dev/null
        rm -f "$file"
        echo "package-index: issue #$number ($pkg_display) index refreshed."
    else
        echo "package-index: issue #$number ($pkg_display) already current."
    fi
done