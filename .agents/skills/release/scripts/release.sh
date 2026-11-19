#!/usr/bin/env bash
# Prepares a package release: behavior gates, composer.json version bump,
# CHANGELOG [Unreleased] promotion. Never tags or pushes.
#
# Usage:
#   bash release.sh <core|laravel> <version> [--dry-run] [--no-gates]
#
# Examples:
#   bash release.sh core 1.0.0-alpha.2
#   bash release.sh laravel 2.0.0-beta.1 --dry-run

set -euo pipefail

fail() {
  echo "error: $*" >&2
  exit 2
}

ROOT="${ARAZZO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && git rev-parse --show-toplevel)}"

PKG=""
VERSION=""
DRY_RUN=0
NO_GATES=0

for arg in "$@"; do
  case "$arg" in
    --dry-run)  DRY_RUN=1 ;;
    --no-gates) NO_GATES=1 ;;
    *) if [ -z "$PKG" ]; then PKG="$arg"; else VERSION="$arg"; fi ;;
  esac
done

[ "$PKG" = "core" ] || [ "$PKG" = "laravel" ] || fail "package must be 'core' or 'laravel'"
echo "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z][0-9A-Za-z.-]*)?$' \
  || fail "version '$VERSION' is not semver (x.y.z[-suffix])"

COMPOSER="$ROOT/packages/$PKG/composer.json"
CHANGELOG="$ROOT/CHANGELOG.md"
[ -f "$COMPOSER" ]   || fail "cannot read $COMPOSER"
[ -f "$CHANGELOG" ]  || fail "cannot read $CHANGELOG"

NAME="$(grep -m1 -E '"name":[[:space:]]*"' "$COMPOSER" | sed -E 's/.*"name":[[:space:]]*"([^"]+)".*/\1/')"
[ -n "$NAME" ] || fail "cannot determine composer package name"
SUFFIX="${NAME#*/}"

CURRENT="$(grep -m1 -E '"version":[[:space:]]*"' "$COMPOSER" | sed -E 's/.*"version":[[:space:]]*"([^"]*)".*/\1/')"
[ -n "$CURRENT" ] || fail 'no "version" key found in package composer.json'

OLD_VERSION_LINE="$(grep -m1 -E '"version":[[:space:]]*"' "$COMPOSER")"

# --- preconditions --------------------------------------------------------

DIRTY="$(git -C "$ROOT" status --porcelain | grep -v '^??' || true)"
[ -z "$DIRTY" ] || fail "working tree has uncommitted changes:
$DIRTY"

if [ "$NO_GATES" -ne 1 ]; then
  cd "$ROOT"
  echo "== gate: pint =="
  vendor/bin/pint --test || fail "gate 'pint' failed — fix before releasing"
  echo "== gate: phpstan =="
  composer run analyse || fail "gate 'phpstan' failed — fix before releasing"
  echo "== gate: pest =="
  composer run test || fail "gate 'pest' failed — fix before releasing"
fi

UNRELEASED_COUNT="$(grep -c '^## \[Unreleased\]$' "$CHANGELOG" || true)"
[ "$UNRELEASED_COUNT" -eq 1 ] || fail "CHANGELOG.md needs exactly one ## [Unreleased] heading (found $UNRELEASED_COUNT)"

VERSION_KEY_COUNT="$(grep -c -E '^[[:space:]]*"version":[[:space:]]*"' "$COMPOSER" || true)"
[ "$VERSION_KEY_COUNT" -eq 1 ] || fail 'expected exactly one top-level "version" key'

NEW_VERSION_LINE="$(printf '%s\n' "$OLD_VERSION_LINE" | sed -E 's/^([[:space:]]*"version":[[:space:]]*")[^"]*(")/\1'"$VERSION"'\2/')"

DATE="$(date +%Y-%m-%d)"
REPLACEMENT="$(printf '## [Unreleased]\n\n### Added\n\n### Changed\n\n### Fixed\n\n## [%s] - %s' "$VERSION" "$DATE")"

TMP="$(mktemp "${TMPDIR:-/tmp}/changelog.XXXXXX")"
trap 'rm -f "$TMP"' EXIT

export REPLACEMENT
awk '
  $0 == "## [Unreleased]" { printf "%s", ENVIRON["REPLACEMENT"]; print ""; next }
  { print }
' "$CHANGELOG" > "$TMP"

echo "package:  $NAME"
echo "version:  $CURRENT -> $VERSION"

if [ "$DRY_RUN" -eq 1 ]; then
  echo
  echo "--- composer.json (line) ---"
  printf '%s\n%s\n' "$OLD_VERSION_LINE" "$NEW_VERSION_LINE"
  echo
  echo "--- CHANGELOG.md (head) ---"
  head -14 "$TMP"
  echo
  echo "dry run — no files written"
  exit 0
fi

{
  # Rewrite composer.json with only the version line swapped.
  sed -E 's/^([[:space:]]*"version":[[:space:]]*")[^"]*(")/\1'"$VERSION"'\2/' "$COMPOSER"
} > "$COMPOSER.release-tmp" && mv "$COMPOSER.release-tmp" "$COMPOSER"

cp "$TMP" "$CHANGELOG"

echo "written:  packages/$PKG/composer.json"
echo "written:  CHANGELOG.md ([Unreleased] -> [$VERSION] - $DATE)"
echo
echo "next:"
echo "  bash scripts/smoke-install.sh          # clean-install smoke test"
echo "  review docs/release-checklist.md sections 2-5"
echo "  commit, then tag:"
echo "    git tag -a $SUFFIX/$VERSION -m \"$NAME $VERSION\""
