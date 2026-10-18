#!/usr/bin/env bash
#
# The CI PR-linkage backstop (story #213, task #214).
#
# The gh shim hard-blocks an unlinked PR wherever we control the call; this gate
# catches every other source — the web UI, `gh api`, a hand-cut branch. It is
# the CI half of the same hard-block / soft-backstop pairing the intake gate
# uses. It never edits the PR; it only measures.
#
# Inputs (environment):
#   GH_TOKEN            a token with pull-requests:read
#   GITHUB_REPOSITORY   owner/repo (set by Actions)
#   PR_NUMBER           the pull request to check
#   PR_AUTHOR           github.event.pull_request.user.login (optional)
#   PR_LABELS           comma-separated label names (optional)
#
# GATE_GH_BIN overrides the gh binary (used by the tests).

set -euo pipefail

REPO="${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
PR="${PR_NUMBER:?PR_NUMBER is required}"
AUTHOR="${PR_AUTHOR:-}"
LABELS="${PR_LABELS:-}"
GH="${GATE_GH_BIN:-gh}"

# Automation-owned PRs are out of scope, mirroring the intake gate (A4).
case ",$LABELS," in
    *,ecosystem,*)
        echo "PR #$PR is ecosystem-labelled; exempt."
        exit 0
        ;;
esac

case "$AUTHOR" in
    *"[bot]")
        echo "PR #$PR is bot-authored; exempt."
        exit 0
        ;;
esac

count=$("$GH" pr view "$PR" --repo "$REPO" --json closingIssuesReferences --jq '.closingIssuesReferences | length')

if [ "${count:-0}" -eq 0 ]; then
    echo "::error::PR #$PR closes no issue. Add a closing reference to the body, e.g. 'Closes #123'."
    cat <<'EOF'

Every pull request must be traceable to the issue it resolves. Paved path:

    gh issue develop <issue> --checkout
    gh pr create --title "…" --body "Closes #<issue>"
EOF
    exit 1
fi

echo "PR #$PR closes $count issue(s)."
