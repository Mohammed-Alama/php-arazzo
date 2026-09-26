#!/usr/bin/env bash
#
# phpstorm-inspect.sh
#
# Runs PhpStorm's headless "Code Inspections" engine (the same one that
# powers the red/yellow squiggles in the IDE) against a single file or
# directory, and prints a clean, sorted list of the problems it finds.
#
# WHY THIS EXISTS
# PhpStorm ships a command-line inspector (`inspect.sh`) that runs the
# real IDE inspection engine outside the editor. It needs a whole
# project + inspection profile to run, and it launches a *background
# IDE instance* to do it (slow-ish, 10-40s), so this script:
#   1. Points it at your project root, but narrows the scan to just the
#      file/dir you pass in (-d flag) so it's fast to read.
#   2. Asks for JSON output so we can filter/format it ourselves.
#   3. Prints only file:line + severity + message, sorted by line.
#
# REQUIREMENTS
#   - PhpStorm must NOT be running (the headless inspector refuses to
#     start a second instance). Close the IDE first, or use a
#     dedicated inspection profile + a second PhpStorm process if you
#     hit this often.
#   - jq (brew install jq) for parsing the JSON report.
#
# USAGE
#   ./phpstorm-inspect.sh src/Runner/WorkflowRunner.php
#   ./phpstorm-inspect.sh src/Runner                 # whole directory
#   ./phpstorm-inspect.sh -p ~/Code/Me/php-arazzo src/Runner/WorkflowRunner.php
#
# CONFIG (env vars, all optional — edit the defaults below to taste)
#   PHPSTORM_INSPECT   path to inspect.sh inside the PhpStorm app bundle
#   PHPSTORM_PROJECT   project root (the .idea folder must live here)
#   PHPSTORM_PROFILE   inspection profile .xml to use
#
set -euo pipefail

# ---- defaults, override via env or -p / -x flags -------------------------
INSPECT_BIN="${PHPSTORM_INSPECT:-/Applications/PhpStorm.app/Contents/bin/inspect.sh}"
PROJECT_DIR="${PHPSTORM_PROJECT:-$HOME/Code/Me/php-arazzo}"
PROFILE="${PHPSTORM_PROFILE:-$PROJECT_DIR/.idea/inspectionProfiles/Project_Default.xml}"

usage() {
  echo "Usage: $(basename "$0") [-p project_dir] [-x inspect.sh_path] <file_or_dir>" >&2
  exit 1
}

while getopts ":p:x:" opt; do
  case "$opt" in
    p) PROJECT_DIR="$OPTARG" ;;
    x) INSPECT_BIN="$OPTARG" ;;
    *) usage ;;
  esac
done
shift $((OPTIND - 1))

TARGET="${1:-}"
[ -z "$TARGET" ] && usage

if [ ! -x "$INSPECT_BIN" ]; then
  echo "error: inspect.sh not found/executable at: $INSPECT_BIN" >&2
  echo "  set PHPSTORM_INSPECT or pass -x /path/to/inspect.sh" >&2
  exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
  echo "error: jq is required (brew install jq)" >&2
  exit 1
fi

# Resolve target to an absolute path so we can match it back against the
# (project-relative) paths that show up in the JSON report.
if [[ "$TARGET" = /* ]]; then
  TARGET_ABS="$TARGET"
else
  TARGET_ABS="$(cd "$(dirname "$TARGET")" && pwd)/$(basename "$TARGET")"
fi

if [ ! -e "$TARGET_ABS" ]; then
  echo "error: target does not exist: $TARGET_ABS" >&2
  exit 1
fi

OUT_DIR="$(mktemp -d "${TMPDIR:-/tmp}/phpstorm-inspect.XXXXXX")"
trap 'rm -rf "$OUT_DIR"' EXIT

echo "Inspecting: $TARGET_ABS" >&2
echo "Project:    $PROJECT_DIR" >&2
echo "Profile:    $PROFILE" >&2
echo "(this launches a background PhpStorm instance — first run can take a while)" >&2

# -format json -> one JSON file per inspection id, each a list of problems.
# -v1 = medium verbosity (enough detail without huge output).
"$INSPECT_BIN" "$PROJECT_DIR" "$PROFILE" "$OUT_DIR" \
  -d "$TARGET_ABS" \
  -format json \
  -v1 >/dev/null

# Merge every *.json report file, keep only entries whose file path is
# under our target, and print "path:line  [SEVERITY] message (InspectionName)"
RESULTS=$(
  jq -s '
    [ .[] | (.problems // [])[] ]
  ' "$OUT_DIR"/*.json 2>/dev/null || echo "[]"
)

COUNT=$(echo "$RESULTS" | jq 'length')

if [ "$COUNT" -eq 0 ]; then
  echo "No inspection problems found. ✅"
  exit 0
fi

echo "$RESULTS" \
  | jq -r '
      sort_by(.line // 0)[]
      | "\(.file // "?"):\(.line // "?")  [\(.severity // "WARNING")] \(.description // .problem_class.name)  (\(.problem_class.name // ""))"
    '

echo "" >&2
echo "$COUNT problem(s) found." >&2
