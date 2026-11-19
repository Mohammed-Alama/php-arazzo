#!/usr/bin/env bash
# Regenerate docs/generated/ and fail if it drifted from what is committed.
#
# Usage:
#   bash sync-docs.sh          # regenerate; exit 1 + file list on drift

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && git rev-parse --show-toplevel)"
cd "$ROOT"

php scripts/generate-docs.php

if [ -n "$(git status --porcelain docs/generated)" ]; then
  echo
  echo "docs/generated was stale — regenerated files:"
  git status --short docs/generated | sed 's/^/  /'
  echo
  echo "stage them: git add docs/generated"
  exit 1
fi

echo "docs/generated is in sync."
