#!/usr/bin/env bash
# ship-plan.sh — deterministic promotion of a completed plan into CHANGELOG.
#
# Usage:
#   scripts/ship-plan.sh <plan-slug> [--dry-run] [--no-git]
#
# Behaviour (idempotent, no interactive prompts):
#   1. Find plan file(s) in docs/superpowers/plans/ matching *<slug>*.md
#   2. Find matching design spec in docs/superpowers/specs/ (optional)
#   3. Find matching roadmap stub anywhere under docs/superpowers/roadmap/**/*<slug>*.md (optional)
#   4. Extract title (first `# ` heading) + summary (first non-empty paragraph after title,
#      or contents of a `## Summary` section if present).
#   5. Move plan  → docs/superpowers/plans/shipped/<basename>
#      Move spec  → docs/superpowers/specs/shipped/<basename>  (if present)
#      Delete roadmap stub                                    (if present)
#   6. Insert `- **<Title>** — <summary>` under `## Unreleased` → `### Shipped`
#      in CHANGELOG.md. Section is created if missing. Duplicate entries are skipped.
#   7. `git add -A` the touched paths (unless --no-git).
#   8. Print a short diff summary.
#
# Exit codes:
#   0 success (or already shipped)
#   1 usage / missing slug
#   2 slug matched zero plans
#   3 slug matched multiple plans ambiguously
#   4 CHANGELOG.md missing

set -euo pipefail

usage() {
    cat <<'USAGE'
Usage: scripts/ship-plan.sh <plan-slug> [--dry-run] [--no-git]

Arguments:
  <plan-slug>   Substring matching a plan filename in docs/superpowers/plans/
                (e.g. "idempotency-replay-safeguards" or "2026-07-24-idempotency").

Flags:
  --dry-run     Print planned actions, touch nothing.
  --no-git      Do everything except `git add`.
USAGE
}

if [ $# -lt 1 ]; then
    usage
    exit 1
fi

SLUG=""
DRY_RUN=0
NO_GIT=0

for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --no-git)  NO_GIT=1 ;;
        -h|--help) usage; exit 0 ;;
        --*)       echo "unknown flag: $arg" >&2; usage; exit 1 ;;
        *)
            if [ -z "$SLUG" ]; then
                SLUG="$arg"
            else
                echo "extra positional arg: $arg" >&2
                exit 1
            fi
            ;;
    esac
done

if [ -z "$SLUG" ]; then
    usage
    exit 1
fi

# Resolve repo root (parent of scripts/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

PLANS_DIR="docs/superpowers/plans"
SPECS_DIR="docs/superpowers/specs"
ROADMAP_DIR="docs/superpowers/roadmap"
CHANGELOG="CHANGELOG.md"

if [ ! -f "$CHANGELOG" ]; then
    echo "error: $CHANGELOG not found at repo root" >&2
    exit 4
fi

# 1. Locate plan(s)
PLAN_MATCHES=()
while IFS= read -r line; do
    [ -n "$line" ] && PLAN_MATCHES+=("$line")
done < <(find "$PLANS_DIR" -maxdepth 1 -type f -name "*${SLUG}*.md" | sort)

if [ "${#PLAN_MATCHES[@]}" -eq 0 ]; then
    # Maybe already shipped?
    SHIPPED_MATCHES=()
    while IFS= read -r line; do
        [ -n "$line" ] && SHIPPED_MATCHES+=("$line")
    done < <(find "$PLANS_DIR/shipped" -maxdepth 1 -type f -name "*${SLUG}*.md" 2>/dev/null | sort)
    if [ "${#SHIPPED_MATCHES[@]}" -gt 0 ]; then
        echo "already shipped: ${SHIPPED_MATCHES[*]}" >&2
        exit 0
    fi
    echo "error: no plan matching '*${SLUG}*.md' in $PLANS_DIR" >&2
    exit 2
fi

if [ "${#PLAN_MATCHES[@]}" -gt 1 ]; then
    echo "error: slug '$SLUG' matched multiple plans, be more specific:" >&2
    printf '  %s\n' "${PLAN_MATCHES[@]}" >&2
    exit 3
fi

PLAN_PATH="${PLAN_MATCHES[0]}"
PLAN_BASE="$(basename "$PLAN_PATH")"

# 2. Locate matching spec (best-effort: same slug, may or may not exist)
BASE_NO_EXT="${PLAN_BASE%.md}"
SPEC_PATH=""
CANDIDATE_SPEC="$SPECS_DIR/${BASE_NO_EXT}-design.md"
if [ -f "$CANDIDATE_SPEC" ]; then
    SPEC_PATH="$CANDIDATE_SPEC"
else
    SPEC_MATCHES=()
    while IFS= read -r line; do
        [ -n "$line" ] && SPEC_MATCHES+=("$line")
    done < <(find "$SPECS_DIR" -maxdepth 1 -type f -name "*${SLUG}*.md" | sort)
    if [ "${#SPEC_MATCHES[@]}" -eq 1 ]; then
        SPEC_PATH="${SPEC_MATCHES[0]}"
    fi
fi

# 3. Locate roadmap stub(s) — anywhere under roadmap/
STUB_MATCHES=()
while IFS= read -r line; do
    [ -n "$line" ] && STUB_MATCHES+=("$line")
done < <(find "$ROADMAP_DIR" -type f -name "*${SLUG}*.md" 2>/dev/null | grep -v '/ROADMAP.md$' | sort || true)

# 4. Extract title + summary from plan
TITLE="$(grep -m1 '^# ' "$PLAN_PATH" | sed 's/^# //' || true)"
if [ -z "$TITLE" ]; then
    TITLE="$BASE_NO_EXT"
fi

# Summary extraction. Precedence:
#   (a) plan file's `## Summary` section
#   (b) design spec's `## Purpose` section
#   (c) plan file's first non-blockquote paragraph after the H1
#   (d) fallback stub
# Skip lines that are pure metadata / linkage boilerplate rather than actual content.
META_RE='^(Roadmap|Depends|Related|Status|Date|Author|Goal)'
extract_section() {
    # $1 = file, $2 = section header regex (e.g. '^## Summary')
    awk -v hdr="$2" -v meta="$META_RE" '
        function strip(s) { sub(/^[*]+/, "", s); return s }
        $0 ~ hdr {flag=1; next}
        flag && /^## / {exit}
        flag && /^> / {next}
        flag { line = strip($0); if (line ~ meta) next }
        flag && NF { print }
    ' "$1"
}
extract_first_paragraph() {
    awk -v meta="$META_RE" '
        function strip(s) { sub(/^[*]+/, "", s); return s }
        /^# / {seen_h1=1; next}
        seen_h1 && /^## / {exit}
        seen_h1 && /^> / {next}
        seen_h1 { line = strip($0); if (line ~ meta) next }
        seen_h1 && NF { print; got=1; next }
        seen_h1 && got && !NF { exit }
    ' "$1"
}
clean_paragraph() {
    tr '\n' ' ' | sed 's/  */ /g' | sed 's/^ //;s/ $//' | head -c 400
}

SUMMARY=""
# (a) spec ## Purpose is the truest design intent
if [ -z "$SUMMARY" ] && [ -n "$SPEC_PATH" ] && grep -q '^## Purpose' "$SPEC_PATH"; then
    SUMMARY="$(extract_section "$SPEC_PATH" '^## Purpose' | clean_paragraph)"
fi
# (b) plan ## Summary section
if [ -z "$SUMMARY" ] && grep -q '^## Summary' "$PLAN_PATH"; then
    SUMMARY="$(extract_section "$PLAN_PATH" '^## Summary' | clean_paragraph)"
fi
# (c) spec first paragraph after H1
if [ -z "$SUMMARY" ] && [ -n "$SPEC_PATH" ]; then
    SUMMARY="$(extract_first_paragraph "$SPEC_PATH" | clean_paragraph)"
fi
# (d) plan first paragraph after H1
if [ -z "$SUMMARY" ]; then
    SUMMARY="$(extract_first_paragraph "$PLAN_PATH" | clean_paragraph)"
fi
if [ -z "$SUMMARY" ]; then
    SUMMARY="(no summary in plan — edit CHANGELOG manually)"
fi

# Strip trailing " Implementation Plan" from title for a cleaner CHANGELOG entry.
TITLE="$(echo "$TITLE" | sed -E 's/[[:space:]]+Implementation Plan$//; s/[[:space:]]+—[[:space:]]+Design$//')"

# Compose the CHANGELOG bullet
BULLET="- **${TITLE}** — ${SUMMARY}"

echo "==== ship-plan.sh ===="
echo "slug     : $SLUG"
echo "plan     : $PLAN_PATH"
echo "spec     : ${SPEC_PATH:-<none>}"
echo "stubs    : ${STUB_MATCHES[*]:-<none>}"
echo "title    : $TITLE"
echo "bullet   : $BULLET"
echo "dry-run  : $DRY_RUN"
echo "======================"

# Guard: bullet already present in the Shipped section?
# (Ignores mentions elsewhere — e.g. old "In progress" or "Known design debt" entries.)
ALREADY_IN_CHANGELOG=0
if awk -v title="**${TITLE}**" '
    /^### Shipped[[:space:]]*$/ { flag=1; next }
    flag && /^## / { exit }
    flag && /^### / { exit }
    flag && index($0, title) > 0 { found=1; exit }
    END { exit found ? 0 : 1 }
' "$CHANGELOG"; then
    echo "note: '**${TITLE}**' already in ### Shipped — skipping insert"
    ALREADY_IN_CHANGELOG=1
fi

if [ "$DRY_RUN" -eq 1 ]; then
    echo "(dry-run) no files touched"
    exit 0
fi

# 5. Move files
mkdir -p "$PLANS_DIR/shipped"
PLAN_DEST="$PLANS_DIR/shipped/$PLAN_BASE"
if [ -f "$PLAN_DEST" ]; then
    echo "note: $PLAN_DEST already exists — leaving in place"
else
    mv "$PLAN_PATH" "$PLAN_DEST"
    echo "moved plan → $PLAN_DEST"
fi

if [ -n "$SPEC_PATH" ]; then
    mkdir -p "$SPECS_DIR/shipped"
    SPEC_DEST="$SPECS_DIR/shipped/$(basename "$SPEC_PATH")"
    if [ -f "$SPEC_DEST" ]; then
        echo "note: $SPEC_DEST already exists — leaving in place"
    else
        mv "$SPEC_PATH" "$SPEC_DEST"
        echo "moved spec → $SPEC_DEST"
    fi
fi

if [ "${#STUB_MATCHES[@]}" -gt 0 ]; then
    for STUB in "${STUB_MATCHES[@]}"; do
        rm -f "$STUB"
        echo "removed stub → $STUB"
    done
fi

# 6. Update CHANGELOG
if [ "$ALREADY_IN_CHANGELOG" -eq 0 ]; then
    TMP="$(mktemp)"
    awk -v bullet="$BULLET" '
        BEGIN { unreleased=0; inserted=0; shipped_seen=0 }
        {
            if (!inserted && $0 ~ /^## Unreleased[[:space:]]*$/) {
                print
                unreleased=1
                next
            }
            if (unreleased && !inserted && $0 ~ /^### Shipped[[:space:]]*$/) {
                print
                print ""
                print bullet
                inserted=1
                shipped_seen=1
                next
            }
            # If we hit the next top-level release header before finding ### Shipped, add the section
            if (unreleased && !inserted && $0 ~ /^## /) {
                print "### Shipped"
                print ""
                print bullet
                print ""
                print $0
                inserted=1
                next
            }
            print
        }
        END {
            if (unreleased && !inserted) {
                print ""
                print "### Shipped"
                print ""
                print bullet
            }
        }
    ' "$CHANGELOG" > "$TMP"
    mv "$TMP" "$CHANGELOG"
    echo "updated CHANGELOG.md"
fi

# 7. git add
if [ "$NO_GIT" -eq 0 ] && command -v git >/dev/null 2>&1 && [ -d .git ]; then
    git add -- "$CHANGELOG" "$PLANS_DIR" "$SPECS_DIR" "$ROADMAP_DIR" 2>/dev/null || true
    echo "staged changes (git add)"
fi

# 8. Diff summary
echo
echo "==== summary ===="
git -c color.ui=never status --short docs/superpowers/plans docs/superpowers/specs docs/superpowers/roadmap "$CHANGELOG" 2>/dev/null || true
echo "================="
