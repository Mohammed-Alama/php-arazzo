#!/usr/bin/env bash
# test-scripts.sh — self-test for falsification-testing skill scripts
# Runs static checks + fixture tests for all 6 scripts.
# Usage: bash test-scripts.sh [--verbose]
# Exit: 0 = all checks passed, 1 = any check failed
set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || echo "")"
if [ -z "$ROOT" ] || [ ! -f "$ROOT/packages/core/composer.json" ]; then
  ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
fi

VERBOSE=0
if [ "${1:-}" = "--verbose" ] || [ "${1:-}" = "-v" ]; then VERBOSE=1; fi

PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "  ✓ $1"; }
fail() { FAIL=$((FAIL+1)); echo "  ✗ $1"; echo "    $2" | sed 's/^/    /'; }
run()  { if [ "$VERBOSE" -eq 1 ]; then "$@"; else "$@" >/dev/null 2>&1; fi; }

echo "=== falsification-testing/scripts self-test ==="
echo "root: $ROOT"
echo

# 1 — static syntax
echo "-- 1/6 static syntax --"
bash -n "$SCRIPT_DIR/hume-audit.sh" && ok "bash -n hume-audit.sh" || fail "bash -n hume-audit.sh" "syntax error"
bash -n "$SCRIPT_DIR/delete-fix-check.sh" && ok "bash -n delete-fix-check.sh" || fail "bash -n delete-fix-check.sh" "syntax error"
bash -n "$SCRIPT_DIR/verify-falsification.sh" && ok "bash -n verify-falsification.sh" || fail "bash -n verify-falsification.sh" "syntax error"
bash -n "$SCRIPT_DIR/test-scripts.sh" && ok "bash -n test-scripts.sh" || fail "bash -n test-scripts.sh" "syntax error"
php -l "$SCRIPT_DIR/detect-fake-tests.php" >/dev/null 2>&1 && ok "php -l detect-fake-tests.php" || fail "php -l detect-fake-tests.php" "$(php -l "$SCRIPT_DIR/detect-fake-tests.php" 2>&1)"
php -l "$SCRIPT_DIR/audit-boundaries.php" >/dev/null 2>&1 && ok "php -l audit-boundaries.php" || fail "php -l audit-boundaries.php" "$(php -l "$SCRIPT_DIR/audit-boundaries.php" 2>&1)"
php -l "$SCRIPT_DIR/scaffold-falsification-test.php" >/dev/null 2>&1 && ok "php -l scaffold-falsification-test.php" || fail "php -l scaffold-falsification-test.php" "$(php -l "$SCRIPT_DIR/scaffold-falsification-test.php" 2>&1)"

# 2 — --help / --dry-run contracts
echo
echo "-- 2/6 --help / --dry-run contracts --"
php "$SCRIPT_DIR/scaffold-falsification-test.php" --help 2>&1 | grep -qi "usage" && ok "scaffold --help" || fail "scaffold --help" "no usage"
bash "$SCRIPT_DIR/hume-audit.sh" --help 2>&1 | grep -q "usage" && ok "hume-audit --help" || fail "hume-audit --help" "no usage"
bash "$SCRIPT_DIR/delete-fix-check.sh" --help 2>&1 | grep -q "usage" && ok "delete-fix --help" || fail "delete-fix --help" "no usage"
bash "$SCRIPT_DIR/verify-falsification.sh" --help 2>&1 | grep -q "usage" && ok "verify --help" || fail "verify --help" "no usage"
bash "$SCRIPT_DIR/hume-audit.sh" --dry-run --core 2>&1 | grep -qi "dry.run" && ok "hume-audit --dry-run" || fail "hume-audit --dry-run" "no dry-run output"
php "$SCRIPT_DIR/audit-boundaries.php" --help 2>&1 | grep -qi "usage" && ok "audit-boundaries --help" || fail "audit-boundaries --help" "no usage"

# 3 — fixture: detect-fake must fail on fake, pass on real
echo
echo "-- 3/6 detect-fake fixture --"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cat > "$TMP/fake.php" <<'PHP'
<?php declare(strict_types=1);
it('calls process', function () { $r=doThing(); expect($r)->not->toBeNull(); });
PHP
cat > "$TMP/real.php" <<'PHP'
<?php declare(strict_types=1);
it('returns Retry when successCriteria false', function () {
  $r=Mockery::mock(ExpressionResolverInterface::class);
  [$c,$ok]=$executor->execute($step,$ctx,$doc);
  expect($ok)->toBeFalse();
});
it('handles empty workflows', function () { expect(fn()=> $parser->parse($empty))->toThrow(ParseException::class); });
PHP
php "$SCRIPT_DIR/detect-fake-tests.php" "$TMP/fake.php" >/dev/null 2>&1; ec=$?
if [ "$ec" -eq 1 ]; then ok "detect-fake fails on fake (exit 1)"; else fail "detect-fake fails on fake" "exit $ec expected 1"; fi
php "$SCRIPT_DIR/detect-fake-tests.php" "$TMP/real.php" >/dev/null 2>&1; ec=$?
if [ "$ec" -eq 0 ]; then ok "detect-fake passes on real (exit 0)"; else fail "detect-fake passes on real" "exit $ec expected 0"; fi
php "$SCRIPT_DIR/detect-fake-tests.php" "$TMP/fake.php" --json 2>&1 | grep -q "FAKE-1" && ok "detect-fake --json emits FAKE-1" || fail "detect-fake --json" "no FAKE-1 in json"
php "$SCRIPT_DIR/detect-fake-tests.php" "$TMP/fake.php" --json 2>&1 | grep -q "NAMING" && ok "detect-fake --json emits NAMING" || fail "detect-fake --json NAMING" "no NAMING"
# real repo smoke — must not crash on 200 files
php "$SCRIPT_DIR/detect-fake-tests.php" --all >/dev/null 2>&1; ec=$?
# exit 1 is expected (repo has some single-test files) — crash would be 2
if [ "$ec" -eq 0 ] || [ "$ec" -eq 1 ]; then ok "detect-fake --all on repo (no crash)"; else fail "detect-fake --all" "exit $ec expected 0/1"; fi

# 4 — audit-boundaries
echo
echo "-- 4/6 audit-boundaries --"
php "$SCRIPT_DIR/audit-boundaries.php" WorkflowEngine --json 2>&1 | grep -q '"boundaries"' && ok "audit WorkflowEngine --json" || fail "audit WorkflowEngine" "no boundaries"
php "$SCRIPT_DIR/audit-boundaries.php" WorkflowEngine --json 2>&1 | grep -q 'maxSteps' && ok "audit WorkflowEngine has maxSteps" || fail "audit maxSteps" "missing"
php "$SCRIPT_DIR/audit-boundaries.php" packages/core/src/Runner/Execution/StepExecutor.php --json 2>&1 | grep -q '"misses"' && ok "audit StepExecutor file" || fail "audit StepExecutor" "no misses key"
php "$SCRIPT_DIR/audit-boundaries.php" packages/core/src/Validator/Validator.php 2>&1 | grep -q "Checklist" && ok "audit Validator text" || fail "audit Validator" "no Checklist"

# 5 — scaffold
echo
echo "-- 5/6 scaffold --"
php "$SCRIPT_DIR/scaffold-falsification-test.php" core ScaffoldHarnessTest "harness claim" --dry-run 2>&1 | grep -q "dry-run" && ok "scaffold --dry-run" || fail "scaffold --dry-run" "no dry-run"
php "$SCRIPT_DIR/scaffold-falsification-test.php" core ScaffoldHarnessTest "harness claim" --path "$TMP/ScaffoldHarnessTest.php" >/dev/null 2>&1 && ok "scaffold write" || fail "scaffold write" "failed"
[ -f "$TMP/ScaffoldHarnessTest.php" ] && ok "scaffold file exists" || fail "scaffold file" "missing"
grep -q "declare(strict_types=1)" "$TMP/ScaffoldHarnessTest.php" && ok "scaffold has strict_types" || fail "scaffold strict_types" "missing"
grep -q "it('harness-claim'" "$TMP/ScaffoldHarnessTest.php" && ok "scaffold claim slug" || fail "scaffold claim" "missing"
php -l "$TMP/ScaffoldHarnessTest.php" >/dev/null 2>&1 && ok "scaffold php -l" || fail "scaffold php -l" "syntax error"
# pint must not error (only single_blank_line_at_eof is allowed)
if vendor/bin/pint "$TMP/ScaffoldHarnessTest.php" --test >/dev/null 2>&1; then ok "scaffold pint clean"; else
  # allow single_blank_line_at_eof fixable — check that's the only fixer
  out="$(vendor/bin/pint "$TMP/ScaffoldHarnessTest.php" --test 2>&1 || true)"
  if echo "$out" | grep -q "single_blank_line_at_eof" && ! echo "$out" | grep -q "ordered_imports\|declare_strict"; then ok "scaffold pint (only eof)";
  else fail "scaffold pint" "$out"; fi
fi
php "$SCRIPT_DIR/scaffold-falsification-test.php" laravel ScaffoldLaravelTest "laravel claim" --path "$TMP/ScaffoldLaravelTest.php" --dry-run 2>&1 | grep -q "package: laravel" && ok "scaffold laravel variant" || fail "scaffold laravel" "no package hint"

# 6 — integration dry helpers
echo
echo "-- 6/6 integration dry --"
bash "$SCRIPT_DIR/hume-audit.sh" --dry-run --all 2>&1 | grep -q "Hume audit" && ok "hume-audit dry all" || fail "hume-audit dry" "no audit"
bash "$SCRIPT_DIR/delete-fix-check.sh" --filter "non-existent-xyz" --path packages/core --no-stash 2>&1 | grep -q "RESULT" && ok "delete-fix dry" || fail "delete-fix dry" "no RESULT"
bash "$SCRIPT_DIR/verify-falsification.sh" --help 2>&1 | grep -q "quick" && ok "verify help" || fail "verify help" "no quick"

echo
echo "=== result: $PASS passed, $FAIL failed ==="
if [ "$FAIL" -gt 0 ]; then exit 1; fi
echo "all self-tests passed"
exit 0
