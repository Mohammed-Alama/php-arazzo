#!/usr/bin/env bash
# generate-report.sh — Full human + agent report for comprehensive falsification-testing skill
# Aggregates all 12 scripts into one report.
# Usage:
#   bash generate-report.sh              # human report to stdout
#   bash generate-report.sh --json       # agent JSON to stdout
#   bash generate-report.sh --json --out storage/report.json  # also write file
#   bash generate-report.sh --out storage/report.md            # human to file
#   bash generate-report.sh --package core --json | jq
set -u

ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel 2>/dev/null || echo "")"
if [ -z "$ROOT" ] || [ ! -f "$ROOT/packages/core/composer.json" ]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
fi
SCRIPT_DIR="$ROOT/.agents/skills/falsification-testing/scripts"

JSON=0
OUT=""
PKG="core"
while [ $# -gt 0 ]; do
  case "$1" in
    --json) JSON=1; shift ;;
    --out) OUT="$2"; shift 2 ;;
    --package) PKG="$2"; shift 2 ;;
    --help|-h) echo "usage: bash generate-report.sh [--json] [--out <file>] [--package core|laravel|all]"; exit 0 ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
done

# Helper to run a command and capture JSON safely (even if exit 1)
run_json() {
  local cmd="$1"
  local out
  out=$(bash -c "$cmd" 2>&1 || true)
  # Try to extract JSON part (from first { to last } )
  # If output is not JSON, return as text field
  if echo "$out" | grep -q "^{"; then
    # Find first { and last }
    echo "$out" | sed -n '/^{/,/^}/p' | head -n 200
    # Fallback: if sed fails, just echo raw
    if [ -z "$(echo "$out" | grep "^{")" ]; then
      echo "$out" | python3 -c "import json,sys; print(json.dumps({'raw': sys.stdin.read()}))" 2>/dev/null || echo "{\"raw\": \"$(echo "$out" | head -n 20 | tr -d '\"')\"}"
    fi
  else
    # Try to parse as JSON directly
    if echo "$out" | python3 -m json.tool >/dev/null 2>&1; then
      echo "$out"
    else
      # Wrap raw text as JSON string
      echo "$out" | python3 -c "import json,sys; print(json.dumps({'raw': sys.stdin.read().strip()}))" 2>/dev/null || echo "{\"raw\":\"no json\"}"
    fi
  fi
}

# For JSON mode, collect all
if [ "$JSON" -eq 1 ]; then
  # Run each script with --json where supported
  detect_json=$(php "$SCRIPT_DIR/detect-fake-tests.php" --all --json 2>&1 || true)
  audit_json=$(php "$SCRIPT_DIR/audit-boundaries.php" WorkflowEngine --json 2>&1 || true)
  coverage_json=$(php "$SCRIPT_DIR/query-coverage.php" --overview --package "$PKG" --json 2>&1 || true)
  hotspots_json=$(php "$SCRIPT_DIR/query-coverage.php" --hotspots --limit 5 --package "$PKG" --json 2>&1 || true)
  severity_json=$(php "$SCRIPT_DIR/severity-audit.php" --json 2>&1 || true)
  property_json=$(php "$SCRIPT_DIR/property-audit.php" --json --package "$PKG" 2>&1 || true)
  fuzz_json=$(php "$SCRIPT_DIR/socratic-fuzz.php" --iterations 10 --json 2>&1 || true)
  demon_json=$(php "$SCRIPT_DIR/demon-sim.php" --seeds 3 --json --package "$PKG" 2>&1 || true)
  hume_out=$(bash "$SCRIPT_DIR/hume-audit.sh" --dry-run --all 2>&1 || true)
  hume_json=$(echo "$hume_out" | python3 -c "import json,sys; txt=sys.stdin.read(); print(json.dumps({'raw': txt.strip(), 'dry_run': True}))" 2>/dev/null || echo "{\"raw\":\"$hume_out\"}")

  # Build combined JSON
  combined=$(python3 -c "
import json, sys
detect = json.loads(open('/tmp/detect.json').read()) if False else None
" 2>&1 || true)

  # Simpler: build via python inline
  python3 <<PY
import json, subprocess, pathlib, sys, os

def load_json(cmd):
    import subprocess, json
    try:
        out = subprocess.check_output(cmd, shell=True, stderr=subprocess.STDOUT, text=True)
    except subprocess.CalledProcessError as e:
        out = e.output
    # Try to find JSON object
    out = out.strip()
    # Find first { and last }
    start = out.find('{')
    end = out.rfind('}')
    if start != -1 and end != -1:
        j = out[start:end+1]
        try:
            return json.loads(j)
        except:
            return {"raw": out[:2000], "parse_error": True}
    try:
        return json.loads(out)
    except:
        return {"raw": out[:2000]}

root = "$ROOT"
sd = "$SCRIPT_DIR"
pkg = "$PKG"

data = {}
data["meta"] = {
    "generated_at": __import__("datetime").datetime.utcnow().isoformat() + "Z",
    "package": pkg,
    "skill": "falsification-testing comprehensive (V1+coverage+V2)",
    "scripts": 12
}
data["detect_fake"] = load_json(f"php {sd}/detect-fake-tests.php --all --json")
data["audit_boundaries"] = load_json(f"php {sd}/audit-boundaries.php WorkflowEngine --json")
data["coverage_overview"] = load_json(f"php {sd}/query-coverage.php --overview --package {pkg} --json")
data["coverage_hotspots"] = load_json(f"php {sd}/query-coverage.php --hotspots --limit 5 --package {pkg} --json")
data["severity"] = load_json(f"php {sd}/severity-audit.php --json")
data["property"] = load_json(f"php {sd}/property-audit.php --json --package {pkg}")
data["socratic_fuzz"] = load_json(f"php {sd}/socratic-fuzz.php --iterations 10 --json")
data["demon_sim"] = load_json(f"php {sd}/demon-sim.php --seeds 3 --json --package {pkg}")
# hume as raw
try:
    import subprocess
    hume_out = subprocess.check_output(f"bash {sd}/hume-audit.sh --dry-run --all 2>&1", shell=True, text=True)
except subprocess.CalledProcessError as e:
    hume_out = e.output
data["hume_audit"] = {"raw": hume_out.strip()[:3000], "dry_run": True}

# Summary for quick triage
try:
    fake_violations = len(data["detect_fake"].get("violations", []))
except: fake_violations = -1
try:
    cov = data["coverage_overview"].get("total", {}).get("lines_percent")
except: cov = None
try:
    sev = data["severity"].get("severity")
except: sev = None
try:
    grue = data["property"].get("grue_gaps")
except: grue = None
try:
    kill = data["socratic_fuzz"].get("kill_rate")
except: kill = None
data["summary"] = {
    "fake_violations": fake_violations,
    "coverage_percent": cov,
    "severity": sev,
    "grue_gaps": grue,
    "fuzz_kill_rate": kill,
    "demon_pass": data["demon_sim"].get("pass"),
    "overall": "PASS" if (fake_violations==0 and sev and sev>=0.7 and grue==0) else "NEEDS_WORK"
}

print(json.dumps(data, indent=2))
PY
  # Capture combined to variable and handle --out
  combined_out=$(python3 <<PY
import json, subprocess
def load_json(cmd):
    import subprocess, json
    try:
        out = subprocess.check_output(cmd, shell=True, stderr=subprocess.STDOUT, text=True)
    except subprocess.CalledProcessError as e:
        out = e.output
    out=out.strip()
    s=out.find('{'); e=out.rfind('}')
    if s!=-1 and e!=-1:
        j=out[s:e+1]
        try: return json.loads(j)
        except: return {"raw": out[:2000]}
    try: return json.loads(out)
    except: return {"raw": out[:2000]}
root="$ROOT"; sd="$SCRIPT_DIR"; pkg="$PKG"
import datetime
data={}
data["meta"]={"generated_at": __import__("datetime").datetime.utcnow().isoformat()+"Z","package": pkg,"skill": "falsification-testing comprehensive"}
data["detect_fake"]=load_json(f"php {sd}/detect-fake-tests.php --all --json")
data["audit_boundaries"]=load_json(f"php {sd}/audit-boundaries.php WorkflowEngine --json")
data["coverage_overview"]=load_json(f"php {sd}/query-coverage.php --overview --package {pkg} --json")
data["coverage_hotspots"]=load_json(f"php {sd}/query-coverage.php --hotspots --limit 5 --package {pkg} --json")
data["severity"]=load_json(f"php {sd}/severity-audit.php --json")
data["property"]=load_json(f"php {sd}/property-audit.php --json --package {pkg}")
data["socratic_fuzz"]=load_json(f"php {sd}/socratic-fuzz.php --iterations 10 --json")
data["demon_sim"]=load_json(f"php {sd}/demon-sim.php --seeds 3 --json --package {pkg}")
try:
    import subprocess
    hume_out=subprocess.check_output(f"bash {sd}/hume-audit.sh --dry-run --all 2>&1", shell=True, text=True)
except subprocess.CalledProcessError as e:
    hume_out=e.output
data["hume_audit"]={"raw": hume_out.strip()[:3000],"dry_run": True}
# summary
try: fake_violations=len(data["detect_fake"].get("violations",[]))
except: fake_violations=-1
try: cov=data["coverage_overview"].get("total",{}).get("lines_percent")
except: cov=None
try: sev=data["severity"].get("severity")
except: sev=None
try: grue=data["property"].get("grue_gaps")
except: grue=None
try: kill=data["socratic_fuzz"].get("kill_rate")
except: kill=None
data["summary"]={"fake_violations":fake_violations,"coverage_percent":cov,"severity":sev,"grue_gaps":grue,"fuzz_kill_rate":kill,"demon_pass":data["demon_sim"].get("pass"),"overall": "PASS" if (fake_violations==0 and sev and sev>=0.7 and grue==0) else "NEEDS_WORK"}
print(json.dumps(data, indent=2))
PY
)
  echo "$combined_out"
  if [ -n "$OUT" ]; then
    mkdir -p "$(dirname "$OUT")"
    echo "$combined_out" > "$OUT"
    echo "written $OUT" >&2
  fi
  exit 0
fi

# Human report
cat <<HUMAN
========================================
 Falsification Comprehensive Report (V1+coverage+V2)
 Package: $PKG  $(date -u +"%Y-%m-%dT%H:%M:%SZ")
 Skill: .agents/skills/falsification-testing
========================================

HUMAN

echo "1) Fake Test Detector (Popper)"
echo "----------------------------------------"
php "$SCRIPT_DIR/detect-fake-tests.php" --all 2>&1 | head -n 40
echo ""

echo "2) Hume Boundaries (0/1/max/equal) — WorkflowEngine"
echo "----------------------------------------"
php "$SCRIPT_DIR/audit-boundaries.php" WorkflowEngine 2>&1 | head -n 20
echo ""

echo "3) Coverage Overview (Pest HTML)"
echo "----------------------------------------"
php "$SCRIPT_DIR/query-coverage.php" --overview --package "$PKG" 2>&1 | head -n 20
echo ""

echo "4) Coverage Hotspots (lowest 5)"
echo "----------------------------------------"
php "$SCRIPT_DIR/query-coverage.php" --hotspots --limit 5 --package "$PKG" 2>&1 | head -n 15
echo ""

echo "5) Hume Mutation (dry-run MSI)"
echo "----------------------------------------"
bash "$SCRIPT_DIR/hume-audit.sh" --dry-run --all 2>&1 | head -n 10
echo ""

echo "6) Severity Audit (Lakatos/Mayo)"
echo "----------------------------------------"
php "$SCRIPT_DIR/severity-audit.php" 2>&1 | head -n 10
echo ""

echo "7) Property Audit (Goodman grue)"
echo "----------------------------------------"
php "$SCRIPT_DIR/property-audit.php" --package "$PKG" 2>&1 | head -n 15
echo ""

echo "8) Socratic Fuzz (Hegelian agon, 10 iterations)"
echo "----------------------------------------"
php "$SCRIPT_DIR/socratic-fuzz.php" --iterations 10 2>&1 | head -n 15
echo ""

echo "9) Demon Sim (Cartesian, 3 seeds)"
echo "----------------------------------------"
php "$SCRIPT_DIR/demon-sim.php" --seeds 3 --package "$PKG" 2>&1 | head -n 15
echo ""

echo "========================================"
echo " Human report complete. For agent JSON:"
echo "   bash $SCRIPT_DIR/generate-report.sh --json | jq"
echo "   make report-json"
if [ -n "$OUT" ]; then
  mkdir -p "$(dirname "$OUT")"
  # For human mode, OUT is already handled by caller redirect; if script was called with --out, we need to capture human output
  # This block is no longer needed as caller handles redirection, but keep for compatibility
  echo "human report would be at $OUT (run: bash generate-report.sh > $OUT)" >&2
fi
